<?php

/**
 * @file
 * Lazy-render blocks and "content type" plugins, collectively called "blocks".
 *
 * Rendering can be performed offline, prior to expiration, avoiding front-end
 * load as much as possible.
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2014-2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy;


/**
 * Class Asynchronizer brings asynchronism to the rendering of blocks.
 *
 * @package OSInet\Lazy
 */
class Asynchronizer {

  const DEBUG_HEADER = 'X-Lazy-Block';

  /**
   * Maximum length of time before retrying to acquire a build lock.
   */
  const LOCK_TIMEOUT = 5;

  /**
   * Maximum number of build attempts.
   */
  const MAX_PASSES = 5;

  /**
   * The name of the queue holding the deferred jobs.
   *
   * @var string
   */
  const QUEUE_NAME = 'lazy_blocks';

  /**
   * A callback to perform the block rebuilding.
   *
   * @var string or array (if args)
   */
  protected $builder;

  /**
   * The cache backend used to store block contents.
   *
   * @var \DrupalCacheInterface
   */
  protected $cache;

  /**
   * @var array
   */
  protected $cidGenerator;

  /**
   * A Drupal Domain id within which to rebuild.
   *
   * @var int
   */
  protected $did;

  /**
   * A callable providing a way to get the domain ID with or without Domain.
   *
   * @var \Closure|string
   */
  protected $domainGetter;

  /**
   * A callable providing a way to set the domain ID with or without Domain.
   *
   * @var \Closure|string
   */
  protected $domainSetter;

  /**
   * The number of grace seconds added to the TTL of an entry being rebuilt.
   *
   * @var int
   */
  protected $grace;

  /**
   * The minimum number of seconds left before triggering a rebuild request.
   *
   * @var int
   */
  protected $minimumTtl;

  /**
   * A Domain ID preserved during a masquerade.
   *
   * @see Asynchronizer::masqueradeStart()
   *
   * @var int
   */
  protected $savedDid;

  /**
   * A User ID preserved during a masquerade.
   *
   * @see Asynchronizer::masqueradeStart()
   *
   * @var int
   */
  protected $savedUid;

  /**
   * The initial cache TTL for this content.
   *
   * @var int
   */
  protected $ttl;

  /**
   * A Drupal user id on behalf of which to rebuild.
   *
   * @var int
   */
  protected $uid;

  /**
   * Convert camelCaseName to camel_case_name.
   *
   * @param string $input
   *   The camelCase string to convert.
   *
   * @return string
   *   The decamelized string.
   */
  protected static function decamelize($input) {
    $underscored = preg_replace('/[A-Z]/', '_$0', $input);
    $lower_cased = strtolower($underscored);
    $result = ltrim($lower_cased, '_');
    return $result;
  }

  /**
   * Asynchronizer constructor.
   *
   * @param array|string $builder
   *   The name of the function used to perform a rebuild. Can be a plain
   *   function or static method, but cannot be a Closure as it will be
   *   serialized to the queue.
   * @param \DrupalCacheInterface $cache
   *   The cache bin in which to cache data.
   * @param int $ttl
   *   The initial TTL, in seconds, for the content.
   * @param int $minimum_ttl
   *   The minimum TTL, in seconds, below which a rebuild is triggered.
   * @param int $grace
   *   The TTL grace, in seconds, to be added when a rebuild is triggered.
   * @param int $uid
   *   The user id for which to build.
   * @param int $did
   *   The domain id within which to build.
   * @param callable $cid_generator
   *   (optional) A callable to generate cids based on its arguments.
   * @param mixed ...$generator_args
   *   (optional) Arguments for $cid_generator.
   */
  public function __construct($builder,
    \DrupalCacheInterface $cache,
    $ttl = 3600, $minimum_ttl = 300, $grace = 3600, $uid = NULL, $did = NULL,
    // Support PHP5.4: no splat operator, use func_get_args().
    callable $cid_generator = NULL /* , ...$generator_args */) {

    if (!isset($uid)) {
      $account = isset($GLOBALS['user']) ? $GLOBALS['user'] : drupal_anonymous_user();
      $uid = $account->uid;
    }

    $this->domainGetter = $this->getDomainGetter();
    $this->domainSetter = $this->getDomainSetter();

    if (!isset($did)) {
      $domain = $this->getDomain();
      $did = $domain['domain_id'];
    }

    if (isset($cid_generator)) {
      $args = func_get_args();
      $args = array_slice($args, 8);
      $cid_generator = [
        'callable' => $cid_generator,
        'args' => $args,
      ];
    }

    // __sleep() returns the names of the constructor parameters.
    foreach ($this->__sleep() as $name) {
      $uname = $this->decamelize($name);
      $this->$name = $$uname;
    }
  }

  /**
   * Provide a function returning a domain id.
   *
   * This works whether Domain is installed or not.
   *
   * @return \Closure|string
   *   The callable used to get the domain id.
   */
  protected function getDomainGetter() {
    $result = function_exists('domain_get_domain')
      ? 'domain_get_domain'
      : function () {
        return ['domain_id' => 0];
      };
    return $result;
  }

  /**
   * Provide a function (pretending to) set the domain id.
   *
   * This works whether Domain is installed or not.
   *
   * @return \Closure|string
   *   The callable used to (pretend to) set the domain id.
   */
  protected function getDomainSetter() {
    $result = function_exists('domain_set_domain')
      ? 'domain_set_domain'
      : function ($did) {
        $this->did = $did;
      };
    return $result;
  }

  /**
   * Push a rebuild request to the queue.
   *
   * @param mixed $data
   *   Data representing a block to rebuild.
   * @param string $cid
   *   The cache id under which to store the queued data.
   * @param string $lock_name
   *   The Lazy lock name.
   */
  protected function enqueueRebuild($data, $cid, $lock_name) {
    // Only CACHE_TEMPORARY items can be stale, so no check needed.
    $expire = $data->expire + $this->grace;
    $this->cacheSet($cid, $data->data, $expire);

    /** @var \DrupalQueueInterface $queue */
    $queue = \DrupalQueue::get(static::QUEUE_NAME);

    $lite = $this->prepareQueuing();
    $queue->createItem($lite);

    $this->lockRelease($lock_name);
  }

  /**
   * Getter for $cid.
   *
   * Currently, cache can only vary on builder/domain/user : in the future, it
   * could be useful to depend on other context elements, like the local path,
   * roles, or language. Alternatively, use an external cid generator.
   *
   * @return string
   *   The cached id for this instance.
   */
  public function getCid() {
    if (isset($this->cidGenerator)) {
      $generator = $this->cidGenerator['callable'];
      $args = $this->cidGenerator['args'];
      $ret = call_user_func_array($generator, $args);
    }
    else {
      if (is_array($this->builder)) {
        $builder = $this->builder['function'] . ':' . implode(':',
            $this->builder['args']);
      }
      else {
        $builder = $this->builder;
      }

      $ret = "{$builder}:{$this->did}:{$this->uid}";
    }

    return $ret;
  }

  /**
   * Getter for $did.
   *
   * @return int
   *   The Domain id for this instance, 0 if Domain is not enabled.
   */
  public function getDid() {
    $result = $this->did;
    assert('is_int($result)');
    return $result;
  }

  /**
   * Return the domain array for the default domain.
   *
   * @return int
   *   The domain array, or a stub thereof if Domain is not installed.
   */
  protected function getDomain() {
    $getter = $this->domainGetter;
    $result = $getter();
    return $result;
  }

  /**
   * Getter for $uid.
   *
   * @return int
   *   The user id for this instance.
   */
  public function getUid() {
    return $this->uid;
  }

  /**
   * Drupal cache items are stale if they are not permanent and expire "soon".
   *
   * @param object $cache_item
   *   A cache object in cache_get() format.
   *
   * @return bool
   *   Is a non permanent cache item valid but stale ?
   */
  public function isStale($cache_item) {
    $ret = !empty($cache_item->expire)
      && (REQUEST_TIME > $cache_item->expire - $this->minimumTtl);
    return $ret;
  }

  /**
   * Execute $builder within the context of $this.
   *
   * Caveat: this implementation if not reentrant.
   *
   * @param array|string $builder
   *   The builder used to build this block.
   *
   * @return mixed
   *   The results of the builder.
   */
  public function masquerade($builder) {
    $this->masqueradeStart();

    if (is_array($builder)) {
      $user_func = $builder['function'];
      $args = $builder['args'];
    }
    else {
      $user_func = $builder;
      $args = array();
    }

    $ret = call_user_func_array($user_func, $args);
    $this->masqueradeStop();

    return $ret;
  }

  /**
   * Preserve current Drupal context, then modify it to match $this.
   *
   * This implementation if not reentrant.
   *
   * @see Asynchronizer::masquerade()
   */
  protected function masqueradeStart() {
    $saved_account = $GLOBALS['user'];
    $this->savedUid = empty($saved_account->uid) ? 0 : $saved_account->uid;

    $saved_domain = $this->getDomain();
    $this->savedDid = isset($saved_domain->domain_id) ? $saved_domain->domain_id : NULL;

    $GLOBALS['user'] = user_load($this->getUid());
    $this->setDomain($this->did);
  }

  /**
   * Restored the preserved Drupal context.
   *
   * This implementation if not reentrant.
   *
   * @see Asynchronizer::masquerade()
   */
  protected function masqueradeStop() {
    if (isset($this->savedDid)) {
      $this->setDomain($this->savedDid);
    }
    $GLOBALS['user'] = user_load($this->savedUid);
  }

  /**
   * Delegated version of hook_cron_queue_info().
   *
   * @see lazy_cron_queue_info()
   *
   * @return array
   *   A queue info array, as per hook_cron_queue_info().
   */
  public static function cronQueueInfo() {
    $ret = [
      static::QUEUE_NAME => [
      'worker callback' => [__CLASS__, 'work'],
      'time' => 30,
      'skip on cron' => FALSE,
      ]
    ];
    return $ret;
  }

  /**
   * A facade for core function lock_acquire().
   *
   * @param string $lock_name
   *   The Lazy lock name.
   *
   * @return bool
   *   As per lock_acquire().
   */
  protected function lockAcquire($lock_name) {
    return lock_acquire($lock_name, static::LOCK_TIMEOUT);
  }

  /**
   * An adapter for core function lock_wait().
   *
   * @param string $lock_name
   *   The Lazy lock name.
   */
  protected function lockWait($lock_name) {
    lock_wait($lock_name);
  }

  /**
   * An adapter for core function lock_release().
   *
   * @param string $lock_name
   *   The Lazy lock name.
   */
  protected function lockRelease($lock_name) {
    lock_release($lock_name);
  }

  /**
   * Return built block contents.
   *
   * @return mixed
   *   String or render array in case of success, FALSE in case of failure.
   */
  public function render() {
    $passes = 0;
    $cid = $this->getCid();
    $lock_name = __METHOD__;

    $ret = FALSE;
    while ($passes < static::MAX_PASSES) {
      $cached = $this->cacheGet($cid);

      // No valid fresh data from cache: perform a synchronous build.
      if (empty($cached) || empty($cached->data)) {
        if ($this->lockAcquire($lock_name)) {
          $ret = $this->renderFront($cid, $lock_name);
          break;
        }
        else {
          // Nothing possible right now: just wait on lock before iterating.
          $this->lockWait($lock_name);
        }
      }
      // Valid data from cache: they can be served.
      else {
        $ret = $cached->data;

        // Valid stale data: serve, but refresh.
        if ($this->isStale($cached)) {
          // No one else cares: trigger refresh and add grace to the cache item.
          if ($this->lockAcquire($lock_name)) {
            if ($this->verbose) {
              drupal_add_http_header(static::DEBUG_HEADER, "$cid=STALE-REFRESH");
            }
            $this->enqueueRebuild($cached, $cid, $lock_name);
          }
          else {
            // Someone else is already handling refresh: leave it alone.
            if ($this->verbose) {
              drupal_add_http_header(static::DEBUG_HEADER, "$cid=STALE-BUSY");
            }
          }
          break;
        }
        // Valid fresh data: no extra work needed.
        else {
          if ($this->verbose) {
            drupal_add_http_header(static::DEBUG_HEADER, "$cid-FRESH");
          }
          break;
        }
      }

      $passes++;
    }

    return $ret;
  }

  /**
   * Render content synchronously within the front-end built process.
   *
   * @param string $cid
   *   The id under which to cache the rendered block.
   * @param string $lock_name
   *   The Lazy lock name.
   *
   * @return mixed
   *   The rendered block.
   */
  protected function renderFront($cid, $lock_name) {
    if (is_array($this->builder)) {
      $user_func = $this->builder['function'];
      $args = $this->builder['args'];
    }
    else {
      $user_func = $this->builder;
      $args = array();
    }

    $ret = call_user_func_array($user_func, $args);
    drupal_add_http_header(static::DEBUG_HEADER, "$cid=MISS");
    $this->cacheSet($cid, $ret, REQUEST_TIME + $this->ttl);
    lock_release($lock_name);

    return $ret;
  }

  /**
   * Only serialize the properties needed by the constructor.
   *
   * @return array
   *   The keys to include in serialized versions of the object.
   */
  public function __sleep() {
    $ret = array(
      'builder',
      'cache',
      'did',
      'uid',
      'grace',
      'minimumTtl',
      'ttl',
      'cidGenerator',
    );

    return $ret;
  }

  /**
   * A wrapper for core watchdog() to allow testing methods using logging.
   *
   * Untestable: returns nothing and only invokes procedural code.
   *
   * @param int $level
   *   The event severity level, a WATCHDOG_* constant.
   * @param string $message
   *   The message template.
   * @param array $args
   *   The template arguments.
   *
   * @codeCoverageIgnore
   */
  public function log($level, $message, array $args = []) {
    watchdog(static::QUEUE_NAME, $message, $args, $level);
  }

  /**
   * A facade for core cache_get() function.
   *
   * @param string $cid
   *   The cache id for which to retrieve the data.
   *
   * @return object|FALSE
   *   As per cache_get().
   */
  protected function cacheGet($cid) {
    return $this->cache->get($cid);
  }

  /**
   * An adapter for core cache_set() to allow testing methods using it.
   *
   * Untestable: returns nothing and only invokes procedural code.
   *
   * @param string $cid
   *   The cid under which to cache the data. Use NULL to have it rebuilt.
   * @param mixed $content
   *   The data to cache.
   * @param int $expire
   *   The cached data expiration timestamp.
   *
   * @codeCoverageIgnore
   */
  protected function cacheSet($cid, $content, $expire = NULL) {
    $cid = (isset($cid) ? $cid : $this->getCid());
    $expire = (isset($expire) ? $expire : REQUEST_TIME + $this->ttl);

    $this->cache->set($cid, $content, $expire);
  }

  /**
   * Perform a masqueraded render.
   *
   * @see \Asynchronizer::work()
   */
  public function doWork() {
    $content = $this->masquerade($this->builder);
    $cid = $this->getCid();
    $this->cacheSet($cid, $content);
    $this->log(WATCHDOG_DEBUG, 'Worker built @cid', array('@cid' => $cid));
  }

  /**
   * Set the domain id or pretend to if Domain is not installed.
   *
   * @param int $did
   *   The id of the domain to save.
   */
  protected function setDomain($did) {
    $setter = $this->domainSetter;
    $setter($did);
  }

  /**
   * Removed known non-cacheable members, like closures or the cache service.
   */
  protected function prepareQueuing() {
    $lite = [];
    foreach ($this->__sleep() as $key) {
      $lite[$key] = $this->{$key};
    }

    unset($lite['cache'], $lite['domainGetter'], $lite['domainSetter']);
    return $lite;
  }

  /**
   * Set verbosity.
   *
   * @param bool $is_verbose
   *   Add extra debug information ?
   */
  public function setVerbose($is_verbose) {
    $this->verbose = (bool) $is_verbose;
  }

  /**
   * Queue worker called from runqueue.sh.
   *
   * Since runqueue.sh is not aware of Asynchronizer, it can not create an
   * instance, so this method is just a wrapper delegating to the instance
   * built from the queue item, which is the one actually doing the work, for
   * testability.
   *
   * Since the method is static and returns nothing, it cannot be tested.
   *
   * @param array $item
   *   Depending on the queueing module, it can be an Asynchronizer instance,
   *   or more likely a simple array (MongoDB).
   *
   * @codeCoverageIgnore
   *
   * @see Asynchronizer::cronQueueInfo()
   *
   * @see Asynchronizer::__construct()
   */
  public static function work(array $item) {
    if (is_array($item)) {
      $cache = CacheFactory::create();
      $a = new Asynchronizer(
        $item['builder'],
        $cache,
        $item['ttl'],
        $item['minimumTtl'],
        $item['grace'],
        $item['uid'],
        $item['did'],
        $item['cidGenerator']['callable'],
        $item['cidGenerator']['args']
      );
    }
    $a->doWork();
  }

}

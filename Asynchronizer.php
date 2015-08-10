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

/**
 * Class Asynchronizer brings asynchronism to the rendering of blocks.
 */
class Asynchronizer {
  /**
   * The cache bin used by this class
   */
  const BIN = 'cache_lazy_blocks';

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
   * A Drupal Domain id within which to rebuild.
   *
   * @var int
   */
  protected $did;

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
   * Asynchronizer constructor
   *
   * @param string $builder
   *   The name of the function used to perform a rebuild. Can be a plain
   *   function or static method, but cannot be a Closure as it will be
   *   serialized to the queue.
   * @param int $ttl
   *   The initial TTL, in seconds, for the content.
   * @param int $minimumTtl
   *   The minimum TTL, in seconds, below which a rebuild is triggered.
   * @param int $grace
   *   The TTL grace, in seconds, to be added when a rebuild is triggered.
   * @param int $uid
   *   The user id for which to build.
   * @param int $did
   *   The domain id within which to build.
   */
  public function __construct($builder, $ttl = 3600, $minimumTtl = 300, $grace = 3600, $uid = NULL, $did = NULL) {
    if (!isset($uid)) {
      $account = isset($GLOBALS['user']) ? $GLOBALS['user'] : drupal_anonymous_user();
      /** @noinspection PhpUnusedLocalVariableInspection */
      $uid = $account->uid;
    }
    if (!isset($did)) {
      $domain = domain_get_domain();
      /** @noinspection PhpUnusedLocalVariableInspection */
      $did = $domain['domain_id'];
    }

    // __sleep() returns the names of the constructor parameters.
    foreach ($this->__sleep() as $name) {
      $this->$name = $$name;
    }
  }

  /**
   * Push a rebuild request to the queue.
   *
   * @param $data
   * @param $cid
   * @param $lock_name
   */
  protected function enqueueRebuild($data, $cid, $lock_name) {
    // Only CACHE_TEMPORARY items can be stale, so no check needed.
    $expire = $data->expire + $this->grace;
    $this->cacheSet($data->data, $cid, static::BIN, $expire);
    /** @var \DrupalQueueInterface $queue */
    $queue = DrupalQueue::get(static::QUEUE_NAME);
    $queue->createItem($this);
    $this->lockRelease($lock_name);
  }

  /**
   * Getter for $cid.
   *
   * Currently, cache can only vary on builder/domain/user : in the future, it
   * could be useful to depend on other context elements, like the local path,
   * roles, or language.
   *
   * @return string
   */
  public function getCid() {
    if (is_array($this->builder)) {
      $builder = $this->builder['function'] . ':' . implode(':', $this->builder['args']);
    }
    else {
      $builder = $this->builder;
    }

    $ret = "{$builder}:{$this->did}:{$this->uid}";
    return $ret;
  }

  /**
   * Getter for $did.
   *
   * @return int
   */
  public function getDid() {
    return $this->did;
  }

  /**
   * Getter for $uid.
   *
   * @return int
   */
  public function getUid() {
    return $this->uid;
  }

  /**
   * Drupal cache items are stale if they are not permanent and expire "soon".
   *
   * @param object $cache_item
   *
   * @return bool
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
   * @param string $builder
   *
   * @return mixed
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
   *
   * @return void
   */
  protected function masqueradeStart() {
    $saved_account = $GLOBALS['user'];
    $this->savedUid = empty($saved_account->uid) ? 0 : $saved_account->uid;

    $saved_domain = domain_get_domain();
    $this->savedDid = isset($saved_domain->domain_id) ? $saved_domain->domain_id : NULL;

    $GLOBALS['user'] = user_load($this->getUid());
    domain_set_domain($this->did);
  }

  /**
   * Restored the preserved Drupal context.
   *
   * This implementation if not reentrant.
   *
   * @see Asynchronizer::masquerade()
   *
   * @return void
   */
  protected function masqueradeStop() {
    if (isset($this->savedDid)) {
      domain_set_domain($this->savedDid);
    }
    $GLOBALS['user'] = user_load($this->savedUid);
  }

  /**
   * Delegated version of hook_cron_queue_info().
   *
   * @see lazy_cron_queue_info()
   *
   * @return array
   */
  public static function queueInfo() {
    $ret = array(
      static::QUEUE_NAME => array(
      'worker callback' => array('Asynchronizer', 'work'),
      'time' => 30,
      'skip on cron' => TRUE,
    ));
    return $ret;
  }

  /**
   * A facade for core function lock_acquire().
   *
   * @param $lock_name lock name
   */
  protected function lockAcquire($lock_name) {
    return lock_acquire($lock_name, static::LOCK_TIMEOUT);
  }

  /**
   * An adapter for core function lock_wait().
   *
   * @param $lock_name
   */
  protected function lockWait($lock_name) {
    lock_wait($lock_name);
  }

  /**
   * An adapter for core function lock_release().
   *
   * @param $lock_name
   */
  protected function lockRelease($lock_name) {
    lock_release($lock_name);
  }

  /**
   * Return a rendered block.
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
      $data = $this->cacheGet($cid);
      // No valid data from cache: perform a synchronous build.
      if (empty($data) || empty($data->data)) {
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
        $ret = $data->data;

        // Valid stale data: serve, but refresh.
        if ($this->isStale($data)) {
          // No one else cares: trigger refresh and add grace to the cache item.
          if ($this->lockAcquire($lock_name)) {
            $this->enqueueRebuild($data, $cid, $lock_name);
          }
          // Someone else is already handling refresh: leave it alone.
          break;
        }
        // Valid fresh data: no extra work needed.
        else {
          break;
        }
      }

      $passes++;
    }

    return $ret;
  }

  /**
   * @param $cid
   * @param $lock_name
   *
   * @return mixed
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
    cache_set($cid, $ret, static::BIN, REQUEST_TIME + $this->ttl);
    lock_release($lock_name);

    return $ret;
  }

  /**
   * Only serialize the properties needed by the constructor.
   *
   * @return array
   */
  public function __sleep() {
    $ret = array(
      'builder',
      'did',
      'uid',
      'grace',
      'minimumTtl',
      'ttl',
    );

    return $ret;
  }

  /**
   * A wrapper for core watchdog() to allow testing methods using logging.
   *
   * Untestable: returns nothing and only invokes procedural code.
   *
   * @codeCoverageIgnore
   *
   * @param int $level
   * @param string $message
   * @param array $args
   */
  public function log($level, $message, $args) {
    watchdog(static::QUEUE_NAME, $message, $args, $level);
  }

  /**
   * A facade for core cache_get() function.
   *
   * @param $cid
   */
  protected function cacheGet($cid) {
    return cache_get($cid, static::BIN);
  }

  /**
   * An adapter for core cache_set() to allow testing methods using it.
   *
   * Untestable: returns nothing and only invokes procedural code.
   *
   * @codeCoverageIgnore
   *
   * @param mixed $level
   */
  protected function cacheSet($content, $cid = NULL, $bin = NULL, $expire = NULL) {
    $cid = (isset($cid) ? $cid : $this->getCid());
    $bin = (isset($bin) ? $bin : static::BIN);
    $expire = (isset($expire) ? $expire : REQUEST_TIME + $this->ttl);

    cache_set($cid, $content, $bin, $expire);
  }

  /**
   * @see \Asynchronizer::work()
   */
  public function doWork() {
    $content = $this->masquerade($this->builder);
    $this->cacheSet($content);
    $this->log(WATCHDOG_DEBUG, 'Worker built @cid', array('@cid' => $this->getCid()));
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
   * @codeCoverageIgnore
   *
   * @see Asynchronizer::cronQueueInfo()
   *
   * @see Asynchronizer::__construct()
   *
   * @param Asynchronizer $a
   *   The queued Asynchronizer instance.
   *
   * @return void
   *
   */
  public static function work(Asynchronizer $a) {
    $a->doWork();
  }

}

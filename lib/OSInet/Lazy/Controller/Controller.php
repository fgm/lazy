<?php
/**
 * @file
 * Lazy\Controller allows building of page contents to be done lazily,
 * avoiding front-end load as much as possible.
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy\Controller;

use OSInet\Lazy\Route;
use OSInet\Lazy\Runner\Base as Runner;

/**
 * Class Controller brings asynchronism to the rendering of page contents.
 *
 * @package OSInet\Lazy
 */
abstract class Controller implements Builder {
  /**
   * The cache bin used by this class
   */
  const CACHE_BIN = 'cache_lazy';

  /**
   * Maximum length of time before retrying to acquire a build lock.
   */
  const LOCK_TIMEOUT = 5;

  /**
   * Maximum number of build attempts.
   */
  const MAX_PASSES = 5;

  /**
   * The name of the override controller function.
   */
  const NAME = 'lazy_controller_override';

  /**
   * The name of the queue holding the deferred jobs.
   *
   * @var string
   */
  const QUEUE_NAME = 'lazy';

  /**
   * The maximum execution time for deferred jobs.
   */
  const EXECUTE_TIMEOUT = 30;

  /**
   * @var array
   *   The arguments passed to the controller.
   */
  public $args;

  /**
   * @var string
   *  The controller (page callback) name.
   */
  public $builder;

  /**
   * @var \OSInet\Lazy\Route
   *   The route triggering this action.
   */
  public $route;

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
   * A raw $_GET['q'] preserved during a masquerade.
   *
   * @var string
   */
  protected $savedUnsafeQ;

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
   * @var string
   *   The raw $_GET['q'] for which the controller is instantiated. Well-written
   *   page callbacks should not depend on it, but in the real world, many do,
   *   so it needs to be preserved for queued generation.
   */
  protected $unsafe_q;

  /**
   * Controller constructor
   *
   * @param \OSInet\Lazy\Route $route
   *   The route on which the contents is being built.
   * @param string $builder
   *   The name of the function used to perform a rebuild. Can be a plain
   *   function or static method, but cannot be a Closure as it will be
   *   serialized to the queue.
   * @param array $args
   *   The arguments passed to the original controller. May not be closures, nor
   *   passed by reference, as they will be serialized and used after the
   *   initial requesting page cycle.
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
   * @param string $unsafe_q
   *   The raw $_GET['q'] path.
   */
  public function __construct(Route $route, $builder, array $args = [], $ttl = 3600, $minimumTtl = 300, $grace = 3600, $uid = NULL, $did = NULL, $unsafe_q = NULL) {
    if (!isset($uid)) {
      $account = isset($GLOBALS['user']) ? $GLOBALS['user'] : drupal_anonymous_user();
      /** @noinspection PhpUnusedLocalVariableInspection */
      $uid = $account->uid;
    }

    if (!isset($did)) {
      if (function_exists('domain_get_domain')) {
        $domain = domain_get_domain();
        /** @noinspection PhpUnusedLocalVariableInspection */
        $did = $domain['domain_id'];
      }
      else {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $did = 0;
      }
    }

    if (!isset($unsafe_q)) {
      /** @noinspection PhpUnusedLocalVariableInspection */
      $unsafe_q = isset($_GET['q']) ? $_GET['q'] : '';
    }

    // __sleep() returns the names of the constructor parameters.
    foreach ($this->__sleep() as $name) {
      $this->$name = $$name;
    }
  }

  /**
   * Push a rebuild request to the queue while holding a lock for existing content.
   *
   * @param \stdClass $data
   * @param string $cid
   * @param string $lock_name
   */
  protected function enqueueRebuild($data, $cid, $lock_name) {
    // Only CACHE_TEMPORARY items can be stale, so no check needed.
    watchdog('lazy', "Queueing rebuild for @cid", ['@cid' => $cid], WATCHDOG_DEBUG);
    $expire = $data->expire + $this->grace;
    $this->cacheSet($data->data, $cid, static::CACHE_BIN, $expire);
    $queue = $this->getQueue();
    $queue->createItem($this);
    $this->lockRelease($lock_name);
  }

  /**
   * Getter for $cid.
   *
   * TODO cache varies per-everything : in the future, it
   * needs to depend only on specific context elements, as defined by Route::cache,
   * roles, or language.
   * TODO use a more compact CID format for better efficiency
   *
   * @return string
   */
  public function getCid() {
    $cid = [
      $this->route,
      $this->builder,
      $this->args,
      $this->uid,
      $this->did,
    ];

    $ret = json_encode($cid);
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
   * @return \DrupalQueueInterface
   */
  public function getQueue() {
    /** @var \DrupalQueueInterface $q */
    $q = \DrupalQueue::get(static::QUEUE_NAME);
    return $q;
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
   * @param \stdClass $cache_item
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
   * Caveat: this implementation is not reentrant.
   *
   * @return array
   *   - cid
   *   - content
   */
  public function masquerade() {
    watchdog('lazy', 'Base @class/@method:<pre><code>@controller</code></pre>', [
      '@class' => get_called_class(),
      '@method' => __METHOD__,
      '@controller' => var_export($this, true),
    ], WATCHDOG_DEBUG);

    $this->masqueradeStart();
    $this->route->applyRequirements($this->builder);

    $cid = $this->getCid();

    /* Run strategy can be chosen based on route needs:
     * - normal controllers will run with the "simple" runner
     * - controllers which output data and/or die()/exit() need the "forking" runner
     *
     * TODO However, at this point 'forking' is not complete.
     */
    $runner = Runner::create($this->route->getStrategy());
    $runner->run($this->builder, $this->args);
    $output = $runner->getResult();

    $ret = [ $cid, $output ];

    $this->masqueradeStop();

    return $ret;
  }

  /**
   * Preserve current Drupal context, then modify it to match $this.
   *
   * This implementation if not reentrant.
   *
   * @see Asynchronizer::masquerade()
   * @see arg()
   *
   * @return void
   */
  protected function masqueradeStart() {
    $saved_account = $GLOBALS['user'];
    $this->savedUid = empty($saved_account->uid) ? 0 : $saved_account->uid;

    if (function_exists('domain_get_domain')) {
      $saved_domain = domain_get_domain();
      $this->savedDid = isset($saved_domain->domain_id)
        ? $saved_domain->domain_id
        : NULL;
      $GLOBALS['user'] = user_load($this->getUid());
      domain_set_domain($this->did);
    }
    else {
      $this->savedDid = 0;
      $GLOBALS['user'] = user_load($this->getUid());
    }

    $this->savedUnsafeQ = $_GET['q'];
    drupal_static_reset('arg');
    $_GET['q'] = $this->unsafe_q;
  }

  /**
   * Restored the preserved Drupal context.
   *
   * This implementation if not reentrant.
   *
   * @see Asynchronizer::masquerade()
   * @see arg()
   *
   * @return void
   */
  protected function masqueradeStop() {
    drupal_static_reset('arg');
    $_GET['q'] = $this->savedUnsafeQ;

    if (isset($this->savedDid) && function_exists('domain_set_domain')) {
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
  public static function cronQueueInfo() {
    $ret = array(
      static::QUEUE_NAME => array(
      'worker callback' => array(__CLASS__, 'work'),
      'time' => static::EXECUTE_TIMEOUT,
      'skip on cron' => FALSE,
    ));
    return $ret;
  }

  /**
   * A facade for core function lock_acquire().
   *
   * @param string $lock_name lock name
   *
   * @return bool
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
   * Implements hook_permission().
   */
  public static function permission() {
    $ret = [
      'lazy_front_on_miss' => [
        'title' => t('Trigger front rendering on cache misses'),
        'description' => t('This is the most common choice. You should normally give that permission to anonymous and authenticated users. Without it, content will be replaced by a fixed string.'),
      ],
      'lazy_front_on_expired' => [
        'title' => t('Trigger front rendering on expired cache hits'),
        'description' => t('This is good for editors needing to see changes to pages fast, while still keeping response time low.'),
      ],
      'lazy_front_always' => [
        'title' => t('Trigger front rendering: always'),
        'description' => t('This is the basic Drupal slow rendering, useful only for those who need to see changes applied without delay, at the expense of long page generation times.'),
      ]
    ];

    return $ret;
  }

  /**
   * Render the builder results in a front-end page cycle.
   *
   * @param string $cid
   * @param string $lock_name
   *
   * @return mixed
   */
  protected function renderFront($cid, $lock_name) {
    watchdog('lazy', "renderFront(@cid)", ['@cid' => $cid], WATCHDOG_DEBUG);
    $this->route->applyRequirements($this->builder);
    $ret = call_user_func($this->builder, $this->args, $this->route, get_class($this));
    $this->cacheSet($ret, $cid, static::CACHE_BIN, REQUEST_TIME + $this->ttl);
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
      'route',
      'builder',
      'args',
      'did',
      'uid',
      'grace',
      'minimumTtl',
      'ttl',
      'unsafe_q',
    );

    return $ret;
  }

  /**
   * A facade for core cache_get() function.
   *
   * @param $cid
   */
  protected function cacheGet($cid) {
    return cache_get($cid, static::CACHE_BIN);
  }

  /**
   * An adapter for core cache_set() to allow testing methods using it.
   *
   * Untestable: returns nothing and only invokes procedural code.
   *
   * @codeCoverageIgnore
   *
   * @param $content
   * @param string $cid
   * @param string $bin
   * @param int $expire
   */
  protected function cacheSet($content, $cid = NULL, $bin = NULL, $expire = NULL) {
    if (!isset($cid)) {
      $cid = $this->getCid();
    }
    if (!isset($bin)) {
      $bin = static::CACHE_BIN;
    }
    if (!isset($expire)) {
      $expire = REQUEST_TIME + $this->ttl;
    }

    cache_set($cid, $content, $bin, $expire);
  }

  /**
   * Controller factory.
   *
   * @param \OSInet\Lazy\Route $route
   * @param string $original_controller
   * @param array $args
   * @param int $ttl
   * @param int $minimumTtl
   * @param int $grace
   * @param int $uid
   * @param int $did
   * @param string $unsafe_q
   *
   * @return \OSInet\Lazy\Controller\Builder
   */
  public static function create(Route $route, $original_controller, array $args = [], $ttl = 3600, $minimumTtl = 300, $grace = 3600, $uid = NULL, $did = NULL, $unsafe_q = NULL) {
    if (user_access('lazy_front_always')) {
      $class = 'LiveController';
    }
    elseif (user_access('lazy_front_on_expired')) {
      $class = 'FreshController';
    }
    elseif (user_access('lazy_front_on_miss')) {
      $class = 'MissController';
    }
    else {
      $class = 'StaticController';
    }

    $class = __NAMESPACE__ . "\\$class";
    $ret = new $class($route, $original_controller, $args, $ttl, $minimumTtl, $grace, $uid, $did, $unsafe_q);
    return $ret;
  }

  /**
   * @see \Asynchronizer::work()
   */
  public function doWork() {
    list($cid, $content) = $this->masquerade();
    $this->cacheSet($content, $cid);
    watchdog('lazy', 'Worker built <pre>@cid</pre>', ['@cid' => json_decode($this->getCid())], WATCHDOG_DEBUG);
  }

  /**
   * Queue worker called from runqueue.sh.
   *
   * Since runqueue.sh is not aware of Controller, it can not create an
   * instance, so this method is just a wrapper delegating to the instance
   * built from the queue item, which is the one actually doing the work, for
   * testability.
   *
   * Since the method is static and returns nothing, it cannot be tested.
   *
   * @codeCoverageIgnore
   *
   * @see \OSInet\Lazy\Controller\Controller::cronQueueInfo()
   *
   * @see \OSInet\Lazy\Controller\Controller::__construct()
   *
   * @param \OSInet\Lazy\Controller\Controller $a
   *   The queued Controller instance.
   *
   * @return void
   *
   */
  public static function work(Controller $a) {
    watchdog('lazy', "@class::work(@cid), controller: <pre>@controller</pre>", [
      '@class' => get_class($a),
      '@cid' => $a->getCid(),
      '@controller' => print_r($a, TRUE),
    ], WATCHDOG_DEBUG);
    $a->doWork();
  }
}

<?php
/**
 * @file
 * Controller.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy;

/**
 * Class Controller
 *
 * @package OSInet\Lazy
 */
class Controller {
  const CACHE_BIN = 'cache_lazy';
  const CACHE_LIFETIME = 30;
  const CACHE_FRESH = 15;
  const QUEUE_NAME = 'lazy';
  const LOCK_DELAY = 30;
  const EXECUTE_TIMEOUT = 10;

  /**
   * @var string
   *  The action (page callback) name.
   */
  public $action;

  /**
   * @var string
   *   The name of the route (hook_menu() key) triggering this action.
   */
  public $route;

  /**
   * @param string $route
   * @param string $action
   */
  public function __construct($route, $action) {
    $this->route = $route;
    $this->action = $action;
  }

  /**
   * TODO caching is currently per-everything : extend to support the usual granularities.
   * TODO use a more compact CID format for better efficiency
   *
   * @param string $action
   */
  public function getCid() {
    $account = $GLOBALS['user'];
    $uid = $account->uid;
    $langcode = language_default('language');
    $rids = array_keys($account->roles);
    sort($rids);
    $cid = [
      $this->route,
      $this->action,
      $uid,
      $rids,
      $langcode,
    ];

    return json_encode($cid);
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
   * TODO be more specific
   *
   * @param \OSInet\Lazy\object $hit
   *
   * @return bool
   */
  public function isFresh(\stdClass $hit) {
    $current = REQUEST_TIME;
    $fresh_limit = $hit->created + static::CACHE_FRESH;

    $ret = $current <= $fresh_limit;
    return $ret;
  }

  public function execute(array $args) {
    $cid = $this->getCid();
    $cached = cache_get($cid, static::CACHE_BIN);

    // Cache miss: front-end regeneration needed.
    if ($cached === FALSE) {
      dsm("Front rebuild", $cid);
      $ret = call_user_func_array($this->action, $args);
      cache_set($cid, $ret, static::CACHE_BIN, REQUEST_TIME + static::CACHE_LIFETIME);
    }
    else {
      dsm("TTL: " . ($cached->expire - REQUEST_TIME), $cid);
      if (!$this->isFresh($cached)) {
        dsm('Not fresh');
        if (!lock_acquire(static::QUEUE_NAME, static::LOCK_DELAY)) {
          lock_wait(static::QUEUE_NAME);
          return $this->execute($args);
        }
        else {
          $q = $this->getQueue();
          $q->createItem([$cid => $args]);
          lock_release(static::QUEUE_NAME);
        }
      }
      else {
        dsm("Fresh");
      }

      $ret = $cached->data;
    }

    return $ret;
  }
}

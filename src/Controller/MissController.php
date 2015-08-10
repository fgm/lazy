<?php
/**
 * @file
 * MissController.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy\Controller;

/**
 * Class MissController minimizes front building, but still permits it, by only
 * performing front building in case of a cache miss. It is the default strategy
 * in earlier versions and other packages like Asynchronizer.
 *
 * @package OSInet\Lazy
 */
class MissController extends Controller {
  /**
   * Return built page contents.
   *
   * @return mixed
   *   String or render array in case of success, FALSE in case of failure.
   */
  public function build() {
    $passes = 0;
    $cid = $this->getCid();
    $lock_name = __METHOD__;

    $ret = FALSE;
    while ($passes < static::MAX_PASSES) {
      $cached = $this->cacheGet($cid);
      // No valid data from cache: perform a synchronous build.
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
        if ($this->isStale($cached)) {
          // No one else cares: trigger refresh and add grace to the cache item.
          if ($this->lockAcquire($lock_name)) {
            $this->enqueueRebuild($cached, $cid, $lock_name);
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
}

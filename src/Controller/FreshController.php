<?php
/**
 * @file
 * FreshController.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2014-2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy\Controller;

/**
 * Class FreshController.
 *
 * This is a midpoint between MissController and LiveController as it will serve
 * fresh data from cache, but treat stale date as missing.
 *
 * @package OSInet\Lazy
 */
class FreshController extends Controller {
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

      // No valid fresh data from cache: perform a synchronous build.
      if (empty($cached) || empty($cached->data) || $this->isStale($cached)) {
        if ($this->lockAcquire($lock_name)) {
          $ret = $this->renderFront($cid, $lock_name);
          break;
        }
        else {
          // Nothing possible right now: just wait on lock before iterating.
          $this->lockWait($lock_name);
        }
      }
      // Valid data from cache: they are not stale, so no extra work needed.
      else {
        $ret = $cached->data;
        break;
      }

      $passes++;
    }

    return $ret;
  }

}

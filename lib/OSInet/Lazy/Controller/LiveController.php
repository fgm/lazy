<?php
/**
 * @file
 * LiveController.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy\Controller;

/**
 * Class LiveController implements traditional Drupal content building : it does
 * not make any use of the cache system.
 *
 * @package OSInet\Lazy
 */
class LiveController extends Controller {
  public function build() {
    $ret = call_user_func_array($this->builder, $this->args);
    return $ret;
  }
}

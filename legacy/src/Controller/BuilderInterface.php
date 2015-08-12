<?php
/**
 * @file
 * Contains Builder.
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy\Controller;

/**
 * Interface Builder.
 *
 * @package OSInet\Lazy\Controller
 */
interface BuilderInterface {
  /**
   * Build a render array..
   *
   * @return array
   *   A render array.
   */
  public function build();

}

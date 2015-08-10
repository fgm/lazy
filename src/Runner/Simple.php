<?php
/**
 * @file
 * Simple.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy\Runner;


class Simple extends Base {
  /**
   * @param callable $builder
   * @param array $args
   *
   * @return null
   */
  public function run(callable $builder, array $args = []) {
    $this->result = call_user_func_array($builder, $args);
  }
}

<?php
/**
 * @file
 * Base.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy\Runner;


abstract class Base {

  /**
   * @var array
   *   A render array.
   */
  protected $result;

  /**
   * @param $strategy
   *
   * @return \OSInet\Lazy\Runner\Base
   */
  public static function create($strategy) {
    $class = __NAMESPACE__ . '\\' . ucfirst($strategy);
    return new $class();
  }

  /**
   * @return array
   *   A render array.
   */
  public function getResult() {
    return $this->result;
  }

  /**
   * @param callable $builder
   * @param array $args
   *
   * @return void
   */
  public abstract function run(callable $builder, array $args = []);
}

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

/**
 * Class Base is the base implementation for runners.
 *
 * @package OSInet\Lazy\Runner
 */
abstract class Base {
  /**
   * A render array.
   *
   * @var array
   */
  protected $result;

  /**
   * Runner factory method.
   *
   * @param string $strategy
   *   The name of a runner strategy, based on user access.
   *
   * @return \OSInet\Lazy\Runner\Base
   *   A runner instance.
   */
  public static function create($strategy) {
    $class = __NAMESPACE__ . '\\' . ucfirst($strategy);
    return new $class();
  }

  /**
   * Return the result of a run() execution.
   *
   * @return array
   *   A render array.
   */
  public function getResult() {
    return $this->result;
  }

  /**
   * Run a builder with arguments.
   *
   * Unlike render(), Base::run() implementations store their result in
   * $this->result.
   *
   * @param callable $builder
   *   The builder instance.
   * @param array $args
   *   The builder arguments.
   */
  public abstract function run(callable $builder, array $args = []);

}

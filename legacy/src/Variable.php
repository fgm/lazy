<?php
/**
 * @file
 * Variable.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy;

/**
 * Class Variable is a facade to the Drupal variable_* function group.
 *
 * @package OSInet\Lazy
 */
class Variable {
  const PREFIX = 'lazy-';

  protected static $defaults = [
    'routes' => [],
  ];

  /**
   * Facade for variable_del().
   *
   * @param string $name
   *   The base name of the variable.
   */
  public static function del($name) {
    if (isset(static::$defaults[$name])) {
      unset(static::$defaults[$name]);
    }
    variable_del(static::PREFIX . $name);
  }

  /**
   * Facade for variable_get().
   *
   * @param string $name
   *   The base name of the variable.
   *
   * @return mixed|NULL
   *   - NULL is returned if the variable is not defined.
   */
  public static function get($name) {
    $ret = isset(static::$defaults[$name])
      ? variable_get(static::PREFIX . $name, static::$defaults[$name])
      : NULL;

    return $ret;
  }

  /**
   * Facade for variable_set().
   *
   * @param string $name
   *   The base name of the variable.
   * @param mixed $value
   *   The value to store in the variable.
   */
  public static function set($name, $value) {
    static::$defaults[$name] = $value;
    variable_set(static::PREFIX . $name, $value);
  }

  /**
   * Get the base variable names.
   *
   * @return string[]
   *   The array of base variable names for the modules.
   */
  public static function names() {
    return array_keys(static::$defaults);
  }

  /**
   * Implements hook_install().
   *
   * Store variables in the database, to make them more easily discoverable, for
   * use cases like Strongarm.
   *
   * Variables may be forcefully set from settings.php, so store the current
   * value if any, rather than the raw default. That way, the database will
   * reflect the current situation.
   */
  public static function install() {
    foreach (static::names() as $name) {
      $current = static::get($name);
      static::set($name, $current);
    }
  }

  /**
   * Implements hook_uninstall().
   */
  public static function uninstall() {
    foreach (static::names() as $name) {
      $current = static::get($name);
      static::set($name, $current);
    }
  }

}

<?php
/**
 * @file
 * Route.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy;

/**
 * Class Route represents the combination of a hook_menu() item and context.
 *
 * @package OSInet\Lazy
 */
class Route {
  const DEFAULT_TIMEOUT = 30;

  /**
   * The hook_menu[_alter] normal information.
   *
   * @var array
   */
  public $info;

  /**
   * May route controller die() ?
   *
   * @var bool
   */
  public $isMortal;

  /**
   * The route name, item key in hook_menu[_alter].
   *
   * @var string
   */
  public $name;

  /**
   * The caching policy for route, is a DRUPAL*CACHE* constants.
   *
   * @var int
   */
  public $cache;

  /**
   * The caching TTL for the route, in seconds.
   *
   * @var int
   */
  public $timeout;

  /**
   * Constructor.
   *
   * @param string $name
   *   The route name.
   * @param array $info
   *   The hook_menu() info array for the route.
   * @param int $cache
   *   A Drupal DRUPAL_*CACHE* constant.
   * @param int $timeout
   *   The timeout allowed to build the route.
   */
  public function __construct($name, array $info, $cache = DRUPAL_CACHE_PER_ROLE, $timeout = self::DEFAULT_TIMEOUT, $is_mortal = FALSE) {
    $this->cache = $cache;
    $this->info = $info;
    $this->isMortal = $is_mortal;
    $this->name = $name;
    $this->timeout = $timeout;
  }

  /**
   * The route factory method.
   *
   * @param string $name
   *   The route name.
   * @param array $info
   *   The hook_menu() info array for the route.
   * @param array $alterations
   *   A hash of route alterations defined by the Router instance.
   *
   * @return static
   *   The created instance.
   */
  public static function create($name, array $info, array $alterations = []) {
    $cache = isset($alterations['cache'])
      ? $alterations['cache']
      : DRUPAL_CACHE_PER_ROLE;

    $timeout = isset($alterations['timeout'])
      ? $alterations['timeout']
      : static::DEFAULT_TIMEOUT;

    $is_mortal = isset($alterations['isMortal'])
      ? $alterations['isMortal']
      : FALSE;

    return new static($name, $info, $cache, $timeout, $is_mortal);
  }

  /**
   * Get the route generation strategy.
   *
   * @return string
   *   Runner strategy.
   *
   * @see \OSInet\Lazy\Runner\Base
   */
  public function getStrategy() {
    $ret = $this->isMortal ? 'forking' : 'simple';
    return $ret;
  }

  /**
   * Apply requirements to a route before building its data.
   *
   * @param callable $builder
   *   The builder. If not in scope attempt to load it from the route file path.
   */
  public function applyRequirements(callable $builder) {
    0 && watchdog('lazy', 'Route @class/@method:<pre><code>@controller</code> for @builder</pre>', [
      '@class' => get_called_class(),
      '@method' => __METHOD__,
      '@controller' => var_export($this, TRUE),
      '@builder' => $builder,
    ], WATCHDOG_DEBUG);

    if (is_callable($builder, FALSE)) {
      return;
    }

    $info = $this->info;
    if (isset($info['file'])) {
      $path = isset($info['file path'])
        ? $info['file path']
        : drupal_get_path('module', $info['module']);

      $path .= '/' . $info['file'];

      // TODO : use include_once and trap warnings instead of causing an error.
      require_once $path;

      if (!is_callable($builder)) {
        watchdog('lazy', 'Builder @builder not found after requirements inclusion.', [
          '@builder' => $builder,
        ], WATCHDOG_ERROR);
      }
    }
  }

}

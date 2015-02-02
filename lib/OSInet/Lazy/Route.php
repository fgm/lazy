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


class Route  {
  const DEFAULT_TIMEOUT = 30;

  /**
   * @var array
   *   hook_menu[_alter] normal information
   */
  public $info;

  /**
   * @var string
   *   The route name, item key in hook_menu[_alter]
   */
  public $name;

  /**
   * @var int
   *   The caching policy for route, is a DRUPAL*CACHE* constants.
   */
  public $cache;

  /**
   * @var int
   *   The caching TTL for the route, in seconds.
   */
  public $timeout;

  /**
   * @param string $name
   * @param array $info
   * @param int $cache
   * @param int $timeout
   */
  public function __construct($name, array $info, $cache = DRUPAL_CACHE_PER_ROLE, $timeout = self::DEFAULT_TIMEOUT) {
    $this->name = $name;
    $this->info = $info;
    $this->cache = $cache;
    $this->timeout = $timeout;
  }

  public static function create($name, array $info, array $alterations = []) {
    $cache = isset($alterations['cache'])
      ? $alterations['cache']
      : DRUPAL_CACHE_PER_ROLE;

    $timeout = isset($alterations['timeout'])
      ? $alterations['timeout']
      : static::DEFAULT_TIMEOUT;

    return new static($name, $info, $cache, $timeout);
  }
}

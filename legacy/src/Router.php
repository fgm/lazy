<?php
/**
 * @file
 * Router.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy;


use OSInet\Lazy\Controller\Controller;

/**
 * Class Router builds Route objects from the hook_menu[_alter]() system.
 *
 * @package OSInet\Lazy
 */
class Router {
  public $defaultRoutes;

  public $requestedRoutes;

  /**
   * Constructor.
   *
   * @param array $routes
   *   The available routes in the menu system.
   * @param array $requested
   *   The routes specified as lazy in settings.php.
   */
  public function __construct(array &$routes, array $requested) {
    $this->defaultRoutes = $routes;
    $this->requestedRoutes = $requested;
  }

  /**
   * The routes as modified by hook_menu_alter().
   *
   * @return array
   *   A routes hash.
   */
  public function alteredRoutes() {
    $inter = array_intersect_key($this->defaultRoutes, $this->requestedRoutes);
    foreach ($inter as $name => &$info) {
      if (!isset($info['page callback'])) {
        watchdog('lazy', "Route @route is marked for override, but has no controller defined.", [
          '@route' => $name,
        ], WATCHDOG_ERROR);
        continue;
      }

      $controller_args = isset($info['page arguments'])
        ? $info['page arguments'] : [];
      $controller = $info['page callback'];

      array_unshift($controller_args, $name);
      $info['page callback'] = Controller::NAME;

      $route = Route::create($name, $info, $this->requestedRoutes[$name]);
      array_unshift($controller_args, $route);

      array_unshift($controller_args, $controller);
      $info['page arguments'] = $controller_args;
    }

    return $inter;
  }

}

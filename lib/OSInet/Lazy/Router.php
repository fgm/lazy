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


use Psr\Log\LoggerInterface;

class Router {
  /**
   * @var \Psr\Log\LoggerInterface
   */
  public $logger;

  public $defaultRoutes;

  public $requestedRoutes;

  public function __construct(array &$routes, array $requested, LoggerInterface $logger) {
    $this->defaultRoutes = $routes;
    $this->requestedRoutes = $requested;
    $this->logger = $logger;
  }

  public function alteredRoutes() {
    $inter = array_intersect_key($this->defaultRoutes, $this->requestedRoutes);
    foreach ($inter as $name => &$info) {
      if (!isset($info['page callback'])) {
        $this->logger->warning("Route {route} is marked for override, but has no controller defined.", ['route' => $name]);
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

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
  const CONTROLLER = 'lazy_controller_override';

  /**
   * @var \Psr\Log\LoggerInterface
   */
  public $logger;

  public $defaultRoutes;

  public $alteredRoutes;

  public function __construct(array &$routes, array $altered, LoggerInterface $logger) {
    $this->defaultRoutes = $routes;
    $this->alteredRoutes = $altered;
    $this->logger = $logger;
  }

  public function alteredRoutes() {
    $inter = array_intersect_key($this->defaultRoutes, $this->alteredRoutes);
    foreach ($inter as $name => &$info) {
      if (!isset($info['page callback'])) {
        $this->logger->warning("Route {route} is marked for override, but has no controller defined.", ['route' => $name]);
        continue;
      }

      $controller_args = isset($info['page arguments'])
        ? $info['page arguments'] : [];
      $controller = $info['page callback'];

      array_unshift($controller_args, $name);
      $info['page callback'] = static::CONTROLLER;
      array_unshift($controller_args, $controller);
      $info['page arguments'] = $controller_args;
    }

    return $inter;
  }
}

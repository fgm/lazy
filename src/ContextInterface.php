<?php
/**
 * @file
 * Contains ContextInterface.
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy;


interface ContextInterface {
  public function getDomainId();
  public function getUserId();
  public function getRoleIds();
  public function getRouteName();
}

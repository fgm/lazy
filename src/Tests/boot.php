<?php
/**
 * @file
 * boot.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2014 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

define('DRUPAL_ROOT', realpath(__DIR__ . '/../../../../../../../../..'));

$_SERVER = array(
  'HTTP_HOST' => 'regions_france3',
  'REMOTE_ADDR' => '127.0.0.1',
  'REQUEST_TIME' => time(),
  'SCRIPT_NAME' => __FILE__,
);

chdir(DRUPAL_ROOT);
require_once 'includes/bootstrap.inc';
require_once 'includes/database/database.inc';

drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

require_once __DIR__ . '/../../../../Asynchronizer.php';

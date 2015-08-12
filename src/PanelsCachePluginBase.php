<?php
/**
 * @file
 * Contains PanelsCachePluginBase.
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy;

abstract class PanelsCachePluginBase {
  const CACHE_BIN = 'cache_lazy_panels';

  protected static $instance = NULL;

  protected $cacheBackend;

  /**
   * Constructor.
   *
   * @param \DrupalCacheInterface $cache_backend
   *   The cache backend to use for this cache plugin.
   */
  protected function __construct(\DrupalCacheInterface $cache_backend) {
    $this->cacheBackend = $cache_backend;
  }

  /**
   * Return the plugin definition.
   *
   * This is what is traditionally included in the plugin definition file.
   *
   * @return array
   *
   * @see plugins/cache/lazy_cache.inc
   * @see ctools_get_plugins()
   */
  public static function definition() {
    $ret = [
      'title' => t("Lazy Cache base"),
      'description' => t('This is a base plugin which should not be instantiated.'),
      'cache get' => __CLASS__ .'::staticGet',
      'cache set' => __CLASS__ .'::staticSet',
      'cache clear' => __CLASS__ .'::staticClear',
      'settings form' => __CLASS__ .'::staticSettingsForm',
      'settings form submit' => __CLASS__ .'::staticSettingsFormSubmit',
    ];

    return $ret;
  }

  /**
   * The singleton instance accessor.
   *
   * @return static
   *   The single plugin instance.
   */
  public static function instance() {
    if (!isset(static::$instance)) {
      static::$instance = new static(_cache_get_object(static::CACHE_BIN));
    }

    return static::$instance;
  }

  /**
   * Get cached content.
   */
  public abstract function get($conf, $display, $args, $contexts, $pane = NULL);

  /**
   * Set cached content.
   */
  public abstract function set($conf, $content, $display, $args, $contexts, $pane = NULL);

  /**
   * Clear cached content.
   *
   * Cache clears are always for an entire display, regardless of arguments.
   */
  public abstract function clear($display);

  public abstract function settingsForm($conf, $display, $pid);

  public abstract function settingsFormSubmit(...$args);

  /**
   * Get cached content.
   */
  public static function staticGet($conf, $display, $args, $contexts, $pane = NULL) {
    return static::instance()->get($conf, $display, $args, $contexts, $pane);
  }

  /**
   * Set cached content.
   */
  public static function staticSet($conf, $content, $display, $args, $contexts, $pane = NULL) {
    static::instance()->set($conf, $content, $display, $args, $contexts, $pane);
  }

  /**
   * Clear cached content.
   *
   * Cache clears are always for an entire display, regardless of arguments.
   */
  public static function staticClear($display) {
    static::instance()->clear($display);
  }

  public static function staticSettingsForm($conf, $display, $pid) {
    return static::instance()->settingsForm($conf, $display, $pid);
  }

  public static function staticSettingsFormSubmit(...$args) {
    static::instance()->settingsFormSubmit(...$args);
  }

}

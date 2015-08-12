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


/**
 * Class PanelsCachePluginBase is the base cache plugin class.
 *
 * It currently implements a singleton, assuming a single concrete descendant
 * class used on a given site. This is a strong assumption in general terms, but
 * reasonable given the package delivery context.
 *
 * @package OSInet\Lazy
 */
abstract class PanelsCachePluginBase {
  /**
   * The name of the bin holding the cached data.
   */
  const CACHE_BIN = 'cache_lazy_panels';

  /**
   * The default (currently single) concrete plugin class.
   */
  const DEFAULT_CLASS = __NAMESPACE__ . '\PanelsCachePlugin';

  /**
   * The single (per-page) cache plugin instance.
   *
   * @var \OSInet\Lazy\PanelsCachePluginBase
   */
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
   *   The base definition for all plugins inheriting this one.
   *
   * @see plugins/cache/lazy_cache.inc
   * @see ctools_get_plugins()
   */
  public static function definition() {
    $ret = [
      'title' => t("Lazy Cache base"),
      'description' => t('This is a base plugin which should not be instantiated.'),
      'cache get' => __NAMESPACE__ . '\get',
      'cache set' => __NAMESPACE__ . '\set',
      'cache clear' => __NAMESPACE__ . '\clear',
      'settings form' => __NAMESPACE__ . '\settings_form',
      'settings form submit' => __NAMESPACE__ . '\settings_form_submit',
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
      // Shortcut if instance is created for a concrete descendant class.
      $called = get_called_class();
      // TODO This is a non-pluggable class name, find something more flexible.
      $class = ($called !== __CLASS__) ? $called : static::DEFAULT_CLASS;

      static::$instance = new $class(_cache_get_object(static::CACHE_BIN));
    }

    return static::$instance;
  }

  /**
   * Get cached content.
   *
   * @param array $conf
   *   The cache plugin settings.
   * @param \panels_display $display
   *   The Panels display.
   * @param array $args
   *   The display arguments.
   * @param array $contexts
   *   The applicable CTools contexts.
   * @param null|object $pane
   *   A pane object when getting a pane, NULL for a whole display.
   *
   * @return mixed
   *   The cached contents, as from cache_get(...)->data.
   */
  public abstract function get(array $conf, \panels_display $display, array $args, array $contexts, $pane = NULL);

  /**
   * Set cached content in cache.
   *
   * @param array $conf
   *   The cache plugin settings.
   * @param \panels_cache_object $content
   *   The data to store.
   * @param \panels_display $display
   *   The panels display.
   * @param array $args
   *   The display arguments.
   * @param array $contexts
   *   The applicable CTools contexts.
   * @param null|object $pane
   *   A pane object when getting a pane, NULL for a whole display.
   */
  public abstract function set(array $conf, \panels_cache_object $content, \panels_display $display, array $args, array $contexts, $pane = NULL);

  /**
   * Clear cached content.
   *
   * @param \panels_display $display
   *   Cache clears are always for an entire display, regardless of arguments.
   */
  public abstract function clear(\panels_display $display);

  /**
   * Build a form for the plugin settings.
   *
   * @param array $conf
   *   A settings array for the plugin.
   * @param \panels_display $display
   *   The current Panels display.
   * @param string $pid
   *   The pane id, if a pane. The region id, if a region.
   *
   * @return mixed
   *   A form render array.
   */
  public abstract function settingsForm(array $conf, \panels_display $display, $pid);

  /**
   * Validate (don't submit) the settings form. Panels will store the settings.
   *
   * @param array $conf
   *   A settings array for the plugin.
   */
  public abstract function settingsFormSubmit(array $conf);

}

/**
 * Get cached content.
 *
 * @param array $conf
 *   The cache plugin settings.
 * @param \panels_display $display
 *   The Panels display.
 * @param array $args
 *   The display arguments.
 * @param array $contexts
 *   The applicable CTools contexts.
 * @param null|object $pane
 *   The pane id, if a pane. The region id, if a region. NULL for a whole panel.
 *
 * @return mixed
 *   The cached contents, as from cache_get(...)->data.
 */
function get(array $conf, \panels_display $display, array $args, array $contexts, $pane = NULL) {
  return PanelsCachePluginBase::instance()->get($conf, $display, $args, $contexts, $pane);
}

/**
 * Set cached content in cache.
 *
 * @param array $conf
 *   The cache plugin settings.
 * @param \panels_cache_object $content
 *   The data to store.
 * @param \panels_display $display
 *   The panels display.
 * @param array $args
 *   The display arguments.
 * @param array $contexts
 *   The applicable CTools contexts.
 * @param null|object $pane
 *   The pane id, if a pane. The region id, if a region. NULL for a whole panel.
 */
function set(array $conf, \panels_cache_object $content, \panels_display $display, array $args, array $contexts, $pane = NULL) {
  PanelsCachePluginBase::instance()->set($conf, $content, $display, $args, $contexts, $pane);
}

/**
 * Clear cached content.
 *
 * @param \panels_display $display
 *   Cache clears are always for an entire display, regardless of arguments.
 */
function clear(\panels_display $display) {
  PanelsCachePluginBase::instance()->clear($display);
}

/**
 * Build a form for the plugin settings.
 *
 * @param array $conf
 *   A settings array for the plugin.
 * @param \panels_display $display
 *   The current Panels display.
 * @param string $pid
 *   The pane id, if a pane. The region id, if a region.
 *
 * @return mixed
 *   A form render array.
 */
function settings_form(array $conf, \panels_display $display, $pid) {
  return PanelsCachePluginBase::instance()->settingsForm($conf, $display, $pid);
}

/**
 * Validate (don't submit) the settings form. Panels will store the settings.
 *
 * @param array $conf
 *   A settings array for the plugin.
 */
function settings_form_submit(array $conf) {
  PanelsCachePluginBase::instance()->settingsFormSubmit($conf);
}

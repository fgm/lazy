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
 * PanelsCachePluginInterface is the OO contract for a Panels cache plugin.
 *
 * It maps directly to the procedural expectations for them: <plugin>_get,
 * <plugin>_set, <plugin>_clear, <plugin>_settings_form[_(validate|submit)].
 *
 * @package OSInet\Lazy
 */
interface PanelsCachePluginInterface {
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
  public function get(array $conf, \panels_display $display, array $args, array $contexts, $pane = NULL);

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
  public function set(array $conf, \panels_cache_object $content, \panels_display $display, array $args, array $contexts, $pane = NULL);

  /**
   * Clear cached content.
   *
   * @param \panels_display $display
   *   Cache clears are always for an entire display, regardless of arguments.
   */
  public function clear(\panels_display $display);

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
  public function settingsForm(array $conf, \panels_display $display, $pid);

  /**
   * (optional) Validate the settings values without the form.
   *
   * For some reason, Panels uses this submit callback as a second validation
   * step; do not use it to store the configuration: Panels handles this itself.
   *
   * @param array $conf
   *   A settings array for the plugin, excerpted from $form_state.
   *
   * @see \OSInet\Lazy\settings_form_submit()
   */
  public function settingsFormSubmit(array $conf);

  /**
   * (optional) Validate the settings form.
   *
   * @param array $form
   *   The settings form render array.
   * @param array $conf
   *   A settings array for the plugin, excerpted from $form_state.
   *
   * @see \OSInet\Lazy\settings_form_validate()
   */
  public function settingsFormValidate(array $form, array $conf);

}

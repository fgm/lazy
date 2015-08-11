<?php
/**
 * @file
 * Contains BlockContent.
 *
 * @author bpresles
 *
 * @copyright (c) 2014-2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy;

/**
 * Class BlockContent provides a basis for build callbacks on CTools plugins.
 *
 * @package OSInet\Lazy
 */
class BlockContent {
  protected $js = [];

  protected  $css = [];

  protected $html = '';

  /**
   * Constructor.
   *
   * @param string $html
   *   A HTML fragment to serve as a block.
   */
  public function __construct($html = '') {
    $this->html = $html;
  }

  /**
   * Add JS to the page holding this block.
   *
   * @param string $type
   *   JS type, as per drupal_add_js().
   * @param mixed $content
   *   JS, as per drupal_add_js().
   * @param array $options
   *   JS options, as per drupal_add_js().
   */
  protected function addJs($type, $content, array $options) {
    if ($type == 'setting') {
      drupal_add_js($content, $type);
    }
    else {
      drupal_add_js($content, $options);
    }
  }

  /**
   * Add CSS to the page holding this block.
   *
   * @param string $path
   *   The path to the CSS file to add.
   * @param array $options
   *   CSS options, as per drupal_add_css().
   */
  protected function addCss($path, array $options) {
    drupal_add_css($path, $options);
  }

  /**
   * Add JS to this block.
   *
   * @param mixed $content
   *   JS, as per drupal_add_js().
   * @param array $options
   *   JS options, as per drupal_add_js().
   */
  public function pushJs($content, $options = array()) {
    $this->js[] = array(
      'type' => (isset($options['type']) ? $options['type'] : 'file'),
      'content' => $content,
      'options' => $options,
    );
  }

  /**
   * Add JS settings to this block.
   *
   * @param array $settings
   *   A JS settings array, as per drupal_add_js().
   * @param string $type
   *   The JS settings type.
   */
  public function pushJsSettings(array $settings, $type) {
    $this->js[] = [
      'type' => $type,
      'content' => $settings,
      'options' => []
    ];
  }

  /**
   * Add CSS to this block.
   *
   * @param string $path
   *   CSS file path, as per drupal_add_css().
   * @param array $options
   *   CSS options, as per drupal_add_css().
   */
  public function pushCss($path, $options = array()) {
    $this->css[] = array(
      'path' => $path,
      'options' => $options,
    );
  }

  /**
   * Add JS and CSS to the page holding the block, from the block data.
   */
  public function generateStatics() {
    // Add JS.
    foreach ($this->js as $js) {
      $this->addJs($js['type'], $js['content'], $js['options']);
    }

    // Add CSS.
    foreach ($this->css as $css) {
      $this->addCss($css['path'], $css['options']);
    }
  }

  /**
   * Set the HTML contents of the block.
   *
   * @param string $html
   *   The HTML fragment on which to build the block.
   */
  public function setHtml($html) {
    $this->html = $html;
  }

  /**
   * Get the HTML contents of the block.
   *
   * @return string
   *   The HTML fragment on which the block is to be built.
   */
  public function getHtml() {
    return $this->html;
  }

}

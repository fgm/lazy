<?php
/**
 * Created by PhpStorm.
 * User: bpresles
 * Date: 18/08/14
 * Time: 10:02
 */

class BlockContent {
  protected $js = array();

  protected  $css =array();

  protected $html = '';

  function __construct($html = '') {
    $this->html = $html;
  }

  protected function addJs($type, $content, $options) {
    if ($type == 'setting') {
      drupal_add_js($content, $type);
    }
    else {
      drupal_add_js($content, $options);
    }
  }

  protected function addCss($path, $options) {
    drupal_add_css($path, $options);
  }

  public function pushJs($path, $options = array()) {
    $this->js[] = array(
      'type' => (isset($options['type']) ? $options['type'] : 'file'),
      'content' => $path,
      'options' => $options
    );
  }

  public function pushJsSettings($settings, $type) {
    $this->js[] = array(
      'type' => $type,
      'content' => $settings,
      'options' => array()
    );
  }

  public function pushCss($path, $options = array()) {
    $this->css[] = array(
      'path' => $path,
      'options' => $options
    );
  }

  public function generateStatics() {
    // Add JS
    foreach ($this->js as $js) {
      $this->addJs($js['type'], $js['content'], $js['options']);
    }

    // Add CSS
    foreach ($this->css as $css) {
      $this->addCss($css['path'], $css['options']);
    }
  }

  /**
   * @param string $html
   */
  public function setHtml($html) {
    $this->html = $html;
  }

  /**
   * @return string
   */
  public function getHtml() {
    return $this->html;
  }
} 
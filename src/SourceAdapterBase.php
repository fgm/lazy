<?php
/**
 * @file
 * SourceAdapterBase.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy;

class SourceAdapterBase implements SourceAdapterInterface {
  /**
   * Build the content, from cache or source depending on availability.
   *
   * @param ...$args
   *   The arguments to pass to the source.
   *
   * @return void
   *   The source will set properties in the instance, not return values.
   */
  public function run(...$args) {
    // TODO: Implement __invoke() method.
  }

  /**
   * Inject the execution context.
   *
   * @param \OSInet\Lazy\Context $context
   *   The global information to set up prior to invoking the adapted source:
   *   domain_id, user_id, role_ids (ordered array), and route,
   */
  public function setContext(ContextInterface $context) {
    // TODO: Implement setContext() method.
  }

  /**
   * Inject the data source.
   *
   * @param callable $source
   *   The adapted data source.
   */
  public function setSource(callable $source) {
    // TODO: Implement setSource() method.
  }

  /**
   * Return the cache id for the combination of source, context, and arguments.
   *
   * @return string
   *   A colon-separated string.
   */
  public function getCid() {
    // TODO: Implement getCid() method.
  }

  /**
   * @return int
   *   One of the SourceAdapterInterface::CACHE_* constants.
   */
  public function getCacheStatus() {
    // TODO: Implement getCacheStatus() method.
  }

  public function getCss() {
    // TODO: Implement getCss() method.
  }

  public function getJs() {
    // TODO: Implement getJs() method.
  }

  public function getHeaders() {
    // TODO: Implement getHeaders() method.
  }

  public function getContent() {
    // TODO: Implement getContent() method.
  }

  public function getTitle() {
    // TODO: Implement getTitle() method.
  }

  public function getAdminTitle() {
    // TODO: Implement getAdminTitle() method.
  }

  public function getAdminInfo() {
    // TODO: Implement getAdminSummary() method.
  }
}

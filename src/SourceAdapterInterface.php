<?php
/**
 * @file
 * SourceAdapterInterface.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy;

/**
 * Interface SourceAdapterInterface is a callable adapter for sources.
 *
 * Classes implementing it are usable by Builders
 *
 * @package OSInet\Lazy
 */
interface SourceAdapterInterface {
  const CACHE_FRESH = 0;
  const CACHE_STALE = 1;
  const CACHE_EXPIRED = 2;
  const CACHE_MISS = 3;

  /**
   * Build the content, from cache or source depending on availability.
   *
   * @param ...$args
   *   The arguments to pass to the source.
   *
   * @return void
   *   The source will set properties in the instance, not return values.
   */
  public function run(...$args);

  /**
   * Inject the execution context.
   *
   * @param \OSInet\Lazy\ContextInterface $context
   *   The global information to set up prior to invoking the adapted source:
   *   domain_id, user_id, role_ids (ordered array), and route,
   */
  public function setContext(ContextInterface $context);

  /**
   * Inject the data source.
   *
   * @param callable $source
   *   The adapted data source.
   */
  public function setSource(callable $source);

  /**
   * Return the cache id for the combination of source, context, and arguments.
   *
   * @return string
   *   A colon-separated string.
   */
  public function getCid();

  /**
   * @return int
   *   One of the SourceAdapterInterface::CACHE_* constants.
   */
  public function getCacheStatus();

  public function getCss();
  public function getJs();
  public function getHeaders();
  public function getContent();
  public function getTitle();
  public function getAdminTitle();
  public function getAdminInfo();


}

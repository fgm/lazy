<?php
/**
 * @file
 * Contains CacheFactory.
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace OSInet\Lazy;

/**
 * Class CacheFactory is a service exposing the D7 cache factory.
 *
 * @package OSInet\Lazy
 */
class CacheFactory {
  /**
   * The cache bin used by this class.
   */
  const BIN = 'cache_lazy_blocks';

  /**
   * Factory method.
   *
   * @param string $bin
   *   The name of the bin on which to provide cache service.
   *
   * @return \DrupalCacheInterface
   *   A cache backend instance.
   */
  public static function create($bin = NULL) {
    if (empty($bin)) {
      $bin = static::BIN;
    }

    return _cache_get_object($bin);
  }

}

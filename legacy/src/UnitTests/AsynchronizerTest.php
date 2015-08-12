<?php
/**
 * @file
 * Contains AsynchronizerTest. Use with PHPUnit, not Simpletest.
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2014-2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace Drupal\lazy\Tests;

use OSInet\Lazy\Asynchronizer;
use OSInet\Lazy\CacheFactory;

/**
 * Class AsynchronizerTest contains unit tests for Asynchronizer.
 *
 * @package Drupal\lazy
 */
class AsynchronizerTest extends \PHPUnit_Framework_TestCase {
  const TESTED_CLASS = '\OSInet\Lazy\Asynchronizer';

  /**
   * A runnable builder instance. Not synonymous with "callable" hint.
   *
   * @var array|string
   */
  protected $builder;

  /**
   * The cache backend used to hold cached block content.
   *
   * @var \DrupalCacheInterface
   */
  protected $cache;

  protected $counter;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->builder = array(__CLASS__, 'build');
    $this->cache = CacheFactory::create();
  }

  /**
   * Helper: Pretend to build a piece of content.
   *
   * @see testWork()
   */
  public static function build() {
    return __METHOD__;
  }

  /**
   * Test constructor.
   */
  public function testConstruct() {}

  /**
   * Test magic __sleep().
   */
  public function testSleep() {}

  /**
   * Test queue submission.
   */
  public function testEnqueueRebuild() {}

  /**
   * Test cache id building/retrieval in Asynchronizer.
   */
  public function testGetCidSpecified() {
    $builder = "somebuilder";
    $ttl = 54321;
    $min_ttl = 12345;
    $grace = 7890;
    $did = 42;
    $uid = 69;

    $expected_cid = "somebuilder:42:69";

    $a = new Asynchronizer($builder, $this->cache, $ttl, $min_ttl, $grace, $uid, $did);
    $this->assertEquals($expected_cid, $a->getCid());
  }

  /**
   * Test the default cid format.
   */
  public function testGetCidDefault() {
    $builder = "somebuilder";
    // boot.php defaults without Domain: {domain}.id is 0 for this site.
    $expected_did = 0;
    $expected_uid = 0;

    $expected_cid = "somebuilder:$expected_did:$expected_uid";

    $a = new Asynchronizer($builder, $this->cache);
    $this->assertEquals($expected_cid, $a->getCid());

  }

  /**
   * Test whether the domain id submitted during build is used when serving.
   */
  public function testGetDidSpecified() {
    $builder = "somebuilder";
    $did = 42;

    $expected_did = $did;
    $a = new Asynchronizer($builder, $this->cache, NULL, NULL, NULL, NULL, $did);
    $this->assertEquals($expected_did, $a->getDid());
  }

  /**
   * Test stale true on stale data.
   */
  public function testIsStaleTrue() {
    $ttl = 1000;
    $min_ttl = 100;
    $a = new Asynchronizer($this->builder, $this->cache, $ttl, $min_ttl);
    $cache_item = (object) array(
      'data' => 'whatever',
      'expire' => REQUEST_TIME + $min_ttl - 1,
    );
    $this->assertTrue($a->isStale($cache_item));
  }

  /**
   * Test stale false on fresh data.
   */
  public function testIsStaleFalse() {
    $ttl = 3600;
    $min_ttl = 300;
    $a = new Asynchronizer($this->builder, $this->cache, $ttl, $min_ttl);
    $cache_item = (object) array(
      'data' => 'whatever',
      'expire' => REQUEST_TIME + $min_ttl + 1,
    );
    $this->assertFalse($a->isStale($cache_item));
  }

  /**
   * Test returning to original context after masquerading.
   *
   * @todo Implement it.
   */
  public function testMasqueradeStop() {}

  /**
   * Test queue definition.
   */
  public function testQueueInfo() {
    $actual = Asynchronizer::queueInfo();
    $this->assertInternalType('array', $actual);
    $this->assertEquals(1, count($actual));
    foreach ($actual as $name => $info) {
      $this->assertEquals($name, Asynchronizer::QUEUE_NAME);
      $this->assertArrayHasKey('worker callback', $info);
      $this->assertArrayHasKey('skip on cron', $info);
      $this->assertEquals(TRUE, $info['skip on cron']);
    }
  }

  /**
   * Test rendering non-cached rendering.
   */
  public function testRenderNoCache() {
    $expected = '<div class="content">block content</div>';

    $mock_methods = [
      'cacheGet',
      'lockAcquire',
      'renderFront',
    ];

    $mock_params = [
      $this->builder,
      $this->cache,
    ];

    $mock = $this->getMock(self::TESTED_CLASS, $mock_methods, $mock_params);

    $mock->expects($this->once())
      ->method('cacheGet')
      ->will($this->returnValue(FALSE));

    $mock->expects($this->once())
      ->method('lockAcquire')
      ->will($this->returnValue(TRUE));

    $mock->expects($this->once())
      ->method('renderFront')
      ->will($this->returnValue($expected));

    $actual = $mock->render();
    $this->assertEquals($expected, $actual);
  }

  /**
   * Test rendering with lock temporary unavailable.
   */
  public function testRenderWaitThreeTimes() {
    $expected = '<div class="content">block content</div>';
    $counter = 1;

    $mock_methods = [
      'cacheGet',
      'lockAcquire',
      'lockWait',
      'renderFront'
    ];

    $mock_params = [
      $this->builder,
      $this->cache,
    ];

    $mock = $this->getMock(self::TESTED_CLASS, $mock_methods, $mock_params);

    $mock->expects($this->exactly(3))
      ->method('cacheGet')
      ->will($this->returnValue(FALSE));

    $mock->expects($this->exactly(3))
      ->method('lockAcquire')
      ->will($this->returnCallback(function() use (&$counter) {
        if ($counter++ < 3) {
          return FALSE;
        }

        return TRUE;
      }));

    $mock->expects($this->exactly(2))
      ->method('lockWait');

    $mock->expects($this->once())
      ->method('renderFront')
      ->will($this->returnValue($expected));

    $actual = $mock->render();
    $this->assertEquals($expected, $actual);
  }

  /**
   * Test lock acquisition failure.
   */
  public function testRenderNeverGetLock() {
    $mock_methods = [
      'cacheGet',
      'lockAcquire',
      'lockWait',
      'renderFront',
    ];

    $mock_params = [
      $this->builder,
      $this->cache,
    ];

    $mock = $this->getMock(self::TESTED_CLASS, $mock_methods, $mock_params);

    $mock->expects($this->exactly(5))
      ->method('cacheGet')
      ->will($this->returnValue(FALSE));

    $mock->expects($this->exactly(5))
      ->method('lockAcquire')
      ->will($this->returnValue(FALSE));

    $mock->expects($this->exactly(5))
      ->method('lockWait');

    $mock->expects($this->exactly(0))
      ->method('renderFront');

    $actual = $mock->render();
    $this->assertFalse($actual);
  }

  /**
   * Test rendering before expiration.
   */
  public function testRenderCacheNotExpired() {
    $expected = '<div class="content">block content</div>';

    $mock_methods = [
      'cacheGet',
      'isStale',
    ];

    $mock_params = [
      $this->builder,
      $this->cache,
    ];

    $mock = $this->getMock(self::TESTED_CLASS, $mock_methods, $mock_params);

    $cache_mocked_value = new \stdClass();
    $cache_mocked_value->data = $expected;

    $mock->expects($this->once())
      ->method('cacheGet')
      ->will($this->returnValue($cache_mocked_value));

    $mock->expects($this->once())
      ->method('isStale')
      ->will($this->returnValue(FALSE));

    $actual = $mock->render();
    $this->assertEquals($expected, $actual);
  }

  /**
   * Test rendering after expiration.
   */
  public function testRenderCacheExpired() {
    $expected = '<div class="content">block content</div>';

    $mock_methods = [
      'cacheGet',
      'isStale',
      'lockAcquire',
      'enqueueRebuild',
    ];

    $mock_params = array(
      $this->builder,
      $this->cache,
    );

    $mock = $this->getMock(self::TESTED_CLASS, $mock_methods, $mock_params);

    $cache_mocked_value = new \stdClass();
    $cache_mocked_value->data = $expected;

    $mock->expects($this->once())
      ->method('cacheGet')
      ->will($this->returnValue($cache_mocked_value));

    $mock->expects($this->once())
      ->method('isStale')
      ->will($this->returnValue(TRUE));

    $mock->expects($this->once())
      ->method('lockAcquire')
      ->will($this->returnValue(TRUE));

    $mock->expects($this->once())
      ->method('enqueueRebuild');

    $actual = $mock->render();
    $this->assertEquals($expected, $actual);
  }

  /**
   * Test front rendering.
   *
   * @TODO Implement it.
   */
  public function testRenderFront() {}

  /**
   * Test Asynchronizer::work().
   */
  public function testWork() {
    $mock_methods = [
      'cacheSet',
    ];
    $mock_params = [
      $this->builder,
      $this->cache,
    ];
    $mock = $this->getMock(self::TESTED_CLASS, $mock_methods, $mock_params);

    $mock->expects($this->once())
      ->method('cacheSet')
      ->will($this->returnValue(NULL));

    $mock->doWork();
  }

}

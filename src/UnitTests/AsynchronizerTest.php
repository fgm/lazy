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

class AsynchronizerTest extends \PHPUnit_Framework_TestCase {
  protected $builder;

  protected $counter;

  public function setUp() {
    $this->builder = array(__CLASS__, 'build');
  }

  /**
   * @see testWork()
   *
   * Pretend to build a piece of content.
   */
  public function build() {
    return __METHOD__;
  }

  public function test__construct() {}
  public function test__sleep() {}
  public function testEnqueueRebuild() {}

  public function testGetCidSpecified() {
    $builder = "somebuilder";
    $ttl = 54321;
    $minTtl = 12345;
    $grace = 7890;
    $did = 42;
    $uid = 69;

    $expected_cid = "somebuilder:42:69";

    $a = new \Asynchronizer($builder, $ttl, $minTtl, $grace, $uid, $did);
    $this->assertEquals($expected_cid, $a->getCid());
  }

  public function testGetCidDefault() {
    $builder = "somebuilder";
    // boot.php defaults without Domain: {domain}.id is 0 for this site.
    $expected_did = 0;
    $expected_uid = 0;

    $expected_cid = "somebuilder:$expected_did:$expected_uid";

    $a = new \Asynchronizer($builder);
    $this->assertEquals($expected_cid, $a->getCid());

  }

  public function testGetDidSpecified() {
    $builder = "somebuilder";
    $did = 42;

    $expected_did = $did;
    $a = new \Asynchronizer($builder, NULL, NULL, NULL, NULL, $did);
    $this->assertEquals($expected_did, $a->getDid());
  }

  public function testIsStaleTrue() {
    $ttl = 1000;
    $minTtl = 100;
    $a = new \Asynchronizer($this->builder, $ttl, $minTtl);
    $cache_item = (object) array(
      'data' => 'whatever',
      'expire' => REQUEST_TIME + $minTtl - 1,
    );
    $this->assertTrue($a->isStale($cache_item));
  }

  public function testIsStaleFalse() {
    $ttl = 3600;
    $minTtl = 300;
    $a = new \Asynchronizer($this->builder, $ttl, $minTtl);
    $cache_item = (object) array(
      'data' => 'whatever',
      'expire' => REQUEST_TIME + $minTtl + 1,
    );
    $this->assertFalse($a->isStale($cache_item));
  }

  public function testMasqueradeStop() {}

  public function testQueueInfo() {
    $actual = \Asynchronizer::queueInfo();
    $this->assertInternalType('array', $actual);
    $this->assertEquals(1, count($actual));
    foreach ($actual as $name => $info) {
      $this->assertEquals($name, \Asynchronizer::QUEUE_NAME);
      $this->assertArrayHasKey('worker callback', $info);
      $this->assertArrayHasKey('skip on cron', $info);
      $this->assertEquals(TRUE, $info['skip on cron']);
    }
  }

  public function testRenderNoCache() {
    $expected = '<div class="content">block content</div>';

    $mockMethods = array(
      'cacheGet',
      'lockAcquire',
      'renderFront'
    );

    $mockParams = array(
      $this->builder,
    );

    $mock = $this->getMock('\Asynchronizer', $mockMethods, $mockParams);

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

  public function testRenderWaitThreeTimes() {
    $expected = '<div class="content">block content</div>';
    $counter = 1;

    $mockMethods = array(
      'cacheGet',
      'lockAcquire',
      'lockWait',
      'renderFront'
    );

    $mockParams = array(
      $this->builder,
    );

    $mock = $this->getMock('\Asynchronizer', $mockMethods, $mockParams);

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

  public function testRenderNeverGetLock() {
    $mockMethods = array(
      'cacheGet',
      'lockAcquire',
      'lockWait',
      'renderFront'
    );

    $mockParams = array(
      $this->builder,
    );

    $mock = $this->getMock('\Asynchronizer', $mockMethods, $mockParams);

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

  public function testRenderCacheNotExpired() {
    $expected = '<div class="content">block content</div>';

    $mockMethods = array(
      'cacheGet',
      'isStale',
    );

    $mockParams = array(
      $this->builder,
    );

    $mock = $this->getMock('\Asynchronizer', $mockMethods, $mockParams);

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

  public function testRenderCacheExpired() {
    $expected = '<div class="content">block content</div>';

    $mockMethods = array(
      'cacheGet',
      'isStale',
      'lockAcquire',
      'enqueueRebuild',
    );

    $mockParams = array(
      $this->builder,
    );

    $mock = $this->getMock('\Asynchronizer', $mockMethods, $mockParams);

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

  public function testRenderFront() {}

  public function testWork() {
    $mockMethods = array(
      'cacheSet',
    );
    $mockParams = array(
      $this->builder,
    );
    $mock = $this->getMock('\Asynchronizer', $mockMethods, $mockParams);

    $mock->expects($this->once())
      ->method('cacheSet')
      ->will($this->returnValue(NULL));

    $mock->doWork();
  }
}

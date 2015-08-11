TESTING
-------

This module uses PHPUnit for tests, not SimpleTest.

* Install PHPunit by running `composer install`
* Copy `phpunit.xml.dist` to `phpunit.xml` if you need to customize it,
* Edit the PHPUnit bootstrap file `src/UnitTests/boot.php` if needed. The most
  usual need is adjusting the relative path to `DRUPAL_ROOT`, which assumes the
  module is placed in `sites/all/modules/osinet/lazy`.
* Run PHPunit, possibly like this:

        vendor/bin/phpunit

You can check test coverage by enabling XDebug and setting `coverage.enabled = 1`
in its configuration file. Current Asynchronizer test coverage is about 70%.

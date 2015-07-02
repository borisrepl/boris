<?php
/*
 * This class tests lib/autoload.php
 *
 * To run this individual test:
 *
 * phpunit --bootstrap tests/autoload.php tests/lib/Boris/ConfigTest --coverage-html ./reports
 *
 */
namespace tests\lib\Boris;
use \Boris\Config as Config;

class ConfigTest
extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests the constructor with no parameters.
     */
    public function test_constructor_no_params () {
      $Config = new Config();

      // since all the class variables are private...
      ob_start();
      print_r($Config);
      $class = ob_get_contents();
      ob_end_clean();

      // check class vars exist
      $this->assertContains('[_searchPaths:Boris\Config:private] => Array', $class);
      $this->assertContains('[_cascade:Boris\Config:private]', $class);
      $this->assertContains('[_files:Boris\Config:private] => Array', $class);

      // check _searchPaths are initialized properly
      $pwd = getcwd() . '/.borisrc';
      $this->assertContains('] => '.$pwd, $class);
      if (getenv('HOME')) {
          $home = getenv('HOME').'/.borisrc';
          $this->assertContains('[0] => '.$home, $class);
      }
    }

    /**
     * Tests the constructor with parameters.
     */
    public function test_constructor_params () {
      // test of good parameters
      $path1 = '/path/tp/awesome';
      $path2 = '/dev/random';
      $Config = new Config(array($path1, $path2), true);

      print_r($Config);
    }

    /**
     * Tests the apply method.
     */
    public function test_apply () {

    }

    /**
     * Tests the loadedFiles method.
     */
    public function test_loadedFiles () {

    }
}

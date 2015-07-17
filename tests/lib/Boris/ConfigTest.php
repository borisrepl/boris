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
      $this->assertContains('] => ' . $pwd, $class);
      if (getenv('HOME')) {
          $home = getenv('HOME').'/.borisrc';
          $this->assertContains('[0] => ' . $home, $class);
      }
    }

    /**
     * Tests the constructor with parameters.
     */
    public function test_constructor_params () {
      // test of good parameters
      $path1 = '/path/to/awesome';
      $path2 = '/dev/random';
      $Config = new Config(array($path1, $path2), true);

      // since all the class variables are private...
      ob_start();
      print_r($Config);
      $class = ob_get_contents();
      ob_end_clean();

      // check class vars exist
      $this->assertContains('[_searchPaths:Boris\Config:private] => Array', $class);
      $this->assertContains('[0] => ' . $path1, $class);
      $this->assertContains('[1] => ' . $path2, $class);
      $this->assertContains('[_cascade:Boris\Config:private] => 1', $class);
      $this->assertContains('[_files:Boris\Config:private] => Array', $class);

      // test of bad params
      $Config = new Config('not an array', 27);

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
      $this->assertContains('] => ' . $pwd, $class);
      if (getenv('HOME')) {
          $home = getenv('HOME').'/.borisrc';
          $this->assertContains('[0] => ' . $home, $class);
      }
    }

    /**
     * Tests the apply method.
     */
    public function test_apply () {
      //setup
      $test_file_to_apply = getcwd() . '/tests/requirable.php';
      $test_file_to_apply2 = getcwd() . '/tests/requirable2.php';
      $files = array($test_file_to_apply, $test_file_to_apply2);

      // without cascade
      $Config = new Config($files);
      $Config->apply();
      $this->assertEquals(getenv('REQUIRED_MESSAGE'), 'You successfully required the file!');

      // with cascade
      $Config = new Config($files, true);
      $Config->apply();
      $this->assertEquals(getenv('REQUIRED_MESSAGE'), 'You successfully required the 2nd file!');
    }

    /**
     * Tests the loadedFiles method.
     */
    public function test_loadedFiles () {
      $test_file_to_apply = getcwd() . '/tests/requirable.php';
      $test_file_to_apply2 = getcwd() . '/tests/requirable2.php';
      $test_file_to_apply3 ='/this/path/is/fake/and/will/fail';
      $files = array($test_file_to_apply, $test_file_to_apply2, $test_file_to_apply3);
      $Config = new Config($files, true);
      $Config->apply();
      $files = $Config->loadedFiles();
      $this->assertEquals(2, count($files));
      $this->assertEquals($test_file_to_apply, $files[0]);
      $this->assertEquals($test_file_to_apply2, $files[1]);
    }
}

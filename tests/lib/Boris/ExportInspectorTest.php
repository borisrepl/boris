<?php
/*
 * This class tests lib/autoload.php
 *
 * To run this individual test:
 *
 * phpunit --bootstrap tests/autoload.php tests/lib/Boris/ExportInspectorTest --coverage-html ./reports
 *
 */
namespace tests\lib\Boris;
use \Boris\ExportInspector as ExportInspector;

class ExportInspectorTest
extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests the inspect method.
     */
    public function test_inspect () {
      $ExportInspector = new ExportInspector();

      // random string
      $variable = rand() . 'test';
      $test = $ExportInspector->inspect($variable);
      $this->assertEquals(" → '$variable'", $test);

      // random integer
      $variable = rand();
      $test = $ExportInspector->inspect($variable);
      $this->assertEquals(" → $variable", $test);

      // random array
      $k = rand();
      $v = rand();
      $variable = array($k=>$v);
      $test = $ExportInspector->inspect($variable);
      $this->assertContains("$k", $test);
      $this->assertContains("$v", $test);

      // object
      $test = $ExportInspector->inspect($ExportInspector);
      $this->assertContains(" → Boris\ExportInspector", $test);
    }
}

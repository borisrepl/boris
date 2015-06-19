<?php
/*
 * This class tests lib/autoload.php
 *
 * To run this individual test:
 *
 * phpunit --bootstrap tests/autoload.php tests/lib/Boris/DumpInspectorTest --coverage-html ./reports
 *
 */
namespace tests\lib\Boris;
use \Boris\DumpInspector as DumpInspector;

class DumpInspectorTest
extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests the inspect method.
     */
    public function test_inspect () {
      $DumpInspector = new DumpInspector();

      // random string
      $variable = rand() . 'test';
      $test = $DumpInspector->inspect($variable);
      $this->assertEquals(' → string('.strlen($variable).') "' . $variable . '"', $test);

      // random integer
      $variable = rand();
      $test = $DumpInspector->inspect($variable);
      $this->assertEquals(" → int($variable)", $test);

      // random float
      $variable = (float) rand();
      $test = $DumpInspector->inspect($variable);
      $this->assertEquals(" → float($variable)", $test);

      // random array
      $k = rand();
      $v = rand();
      $variable = array($k=>$v);
      $test = $DumpInspector->inspect($variable);
      $this->assertContains("$k", $test);
      $this->assertContains("$v", $test);

      // object
      $test = $DumpInspector->inspect($DumpInspector);
      $this->assertContains(" → object(Boris\DumpInspector)", $test);
    }
}

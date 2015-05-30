<?php
/*
 * This class tests lib/autoload.php
 *
 * To run this individual test:
 *
 * phpunit --bootstrap tests/autoload.php --coverage-html ./reports tests/lib/autoloadTest
 *
 */
namespace tests\lib;

class autoloadTest
extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests the autoloading of classes.
     */
    public function testAutoloading () {

        // Classes whose constructors don't require parameters
        $classes = array(
            'Boris',
            'CLIOptionsHandler',
            'ColoredInspector',
            'Config',
            'DumpInspector',
            'ExportInspector',
            'ShallowParser'
        );
        foreach ($classes as $class) {
            $namespaced_class = '\\Boris\\'.$class;
            $test = new $namespaced_class();
            $this->assertEquals('Boris\\'.$class, get_class($test));
        }

        // Classes whose constructors require a socket parameter
        $classes = array(
            'EvalWorker',
            'ReadlineClient'
        );
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $socket = $pipes[1];
        foreach ($classes as $class) {
            $namespaced_class = '\\Boris\\'.$class;
            $test = new $namespaced_class($socket);
            $this->assertEquals('Boris\\'.$class, get_class($test));
        }
    }
}

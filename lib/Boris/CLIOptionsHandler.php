<?php

namespace Boris;

/**
 * Processes available command line flags.
 */
class CLIOptionsHandler
{
    /**
     * Accept the REPL object and perform any setup necessary from the CLI flags.
     *
     * @param Boris $boris
     */
    public function handle($boris)
    {
        $args = getopt('ahvr:', array(
            'autoload',
            'help',
            'version',
            'require:',
        ));
        
        foreach ($args as $option => $value) {
            switch ($option) {
                case 'a':
                case 'autoload':
                    $this->_handleAutoloadOption($boris);
                    break;

                /*
                 * Sets files to load at startup, may be used multiple times,
                 * i.e: boris -r test.php,foo/bar.php -r ba/foo.php --require hey.php
                 */
                case 'r':
                case 'require':
                    $this->_handleRequire($boris, $value);
                    break;
                
                /*
                 * Show Usage info
                 */
                case 'h':
                case 'help':
                    $this->_handleUsageInfo();
                    break;
                
                /*
                 * Show version
                 */
                case 'v':
                case 'version':
                    $this->_handleVersion();
                    break;
            }
        }
    }
    
    // -- Private Methods
    
    private function _handleRequire($boris, $paths)
    {
        $require = array_reduce((array) $paths, function($acc, $v)
        {
            return array_merge($acc, explode(',', $v));
        }, array());
        
        $boris->onStart(function($worker, $scope) use ($require)
        {
            foreach ($require as $path) {
                require $path;
            }
            
            $worker->setLocal(get_defined_vars());
        });
    }
    
    private function _handleUsageInfo()
    {
        echo <<<USAGE
Usage: boris [options]
boris is a tiny REPL for PHP

Options:
  -a, --autoload  If in a composer project, automatically finds and requires autoload.php
  -h, --help      Show this help message and exit
  -r, --require   A comma-separated list of files to require on startup
  -v, --version   Show Boris version

USAGE;
        exit(0);
    }
    
    private function _handleVersion()
    {
        printf("Boris %s\n", Boris::VERSION);
        exit(0);
    }

    private function _handleAutoloadOption($boris)
    {
        $boris->onStart(function() {
            $autoload = null;
            for ($dir = getenv('PWD'); !$autoload && $dir !== '/'; $dir = dirname($dir)) {
                if (file_exists("$dir/autoload.php")) {
                    $autoload = "$dir/autoload.php";
                } else if (file_exists("$dir/vendor/autoload.php")) {
                    $autoload = "$dir/vendor/autoload.php";
                }
            }
            if ($autoload) {
                echo 'Requiring autoload file: ' . $autoload . PHP_EOL;
                require_once $autoload;
            } else {
                echo 'No autoload file found.' . PHP_EOL;
            }
        });
    }
}

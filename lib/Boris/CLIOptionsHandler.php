<?php

/* vim: set shiftwidth=2 expandtab softtabstop=2: */

namespace Boris;

/**
 * Processes available command line flags.
 */
class CLIOptionsHandler {
  /**
   * Accept the REPL object and perform any setup necessary from the CLI flags.
   *
   * @param Boris $boris
   */
  public function handle($boris) {
    $args = getopt('hr:', array('help', 'require:'));

    foreach ($args as $option => $value) {
      switch ($option) {
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
      }
    }
  }

  // -- Private Methods

  private function _handleRequire($boris, $paths) {
    $require = array_reduce(
      (array) $paths,
      function($acc, $v) { return array_merge($acc, explode(',', $v)); },
      array()
    );

    $boris->onStart(function($worker, $scope) use($require) {
      foreach($require as $path) {
        require $path;
      }

      $worker->setLocal(get_defined_vars());
    });
  }

  private function _handleUsageInfo() {
    echo <<<USAGE
Usage: boris [options] [includes]
boris is a tiny REPL for PHP

Options:
  -h, --help     show this help message and exit
  -r, --require  a comma-separated list of files to require on startup

USAGE;
    exit(0);
  }
}

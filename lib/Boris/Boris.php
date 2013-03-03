<?php

namespace Boris;

/**
 * Boris is a tiny REPL for PHP.
 */
class Boris {
  private $_prompt;
  private $_historyFile;
  private $_exports = array();
  private $_inspector;

  /**
   * Create a new REPL, which consists of an evaluation worker and a readline client.
   *
   * @param string $prompt, optional
   * @param string $historyFile, optional
   */
  public function __construct($prompt = 'boris> ', $historyFile = null) {
    $this->_prompt      = $prompt;
    $this->_historyFile = $historyFile
      ? $historyFile
      : sprintf('%s/.boris_history', getenv('HOME'))
      ;
    $this->_inspector = new DumpInspector();
  }

  /**
   * Set a local variable, or many local variables.
   *
   * @example Setting a single variable
   *   $boris->setLocal('user', $bob);
   *
   * @example Setting many variables at once
   *   $boris->setLocal(array('user' => $bob, 'appContext' => $appContext));
   *
   * @param array|string $local
   * @param mixed $value, optional
   */
  public function setLocal($local, $value = null) {
    if (!is_array($local)) {
      $local = array($local => $value);
    }

    $this->_exports = $local;
  }

  /**
   * Set an Inspector object for Boris to output return values with.
   *
   * @param object $inspector any object the responds to inspect($v)
   */
  public function setInspector($inspector) {
    $this->_inspector = $inspector;
  }

  /**
   * Start the REPL (display the readline prompt).
   *
   * This method never returns.
   */
  public function start() {
    declare(ticks = 1);
    pcntl_signal(SIGINT, SIG_IGN, true);

    if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $socks)) {
      throw new \RuntimeException('Failed to create socket pair');
    }

    $pid = pcntl_fork();

    if ($pid > 0) {
      $client = new ReadlineClient($socks[1]);
      $client->start($this->_prompt, $this->_historyFile);
    } elseif ($pid < 0) {
      throw new \RuntimeException('Failed to fork child process');
    } else {
      $worker = new EvalWorker($socks[0]);
      $worker->setExports($this->_exports);
      $worker->setInspector($this->_inspector);
      $worker->start();
    }
  }
}

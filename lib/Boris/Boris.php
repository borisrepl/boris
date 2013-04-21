<?php

/* vim: set shiftwidth=2 expandtab softtabstop=2: */

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
    $this->setPrompt($prompt);
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
   * Sets the Boris prompt text
   *
   * @param string $prompt
   */
  public function setPrompt($prompt) {
    $this->_prompt = $prompt;
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

    if (!$pipes = stream_socket_pair(
      STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) {
      throw new \RuntimeException('Failed to create socket pair');
    }

    $pid = pcntl_fork();

    if ($pid > 0) {
      if (function_exists('setproctitle')) {
        setproctitle('boris (master)');
      }

      fclose($pipes[0]);
      $client = new ReadlineClient($pipes[1]);
      $client->start($this->_prompt, $this->_historyFile);
    } elseif ($pid < 0) {
      throw new \RuntimeException('Failed to fork child process');
    } else {
      if (function_exists('setproctitle')) {
        setproctitle('boris (worker)');
      }

      fclose($pipes[1]);
      $worker = new EvalWorker($pipes[0]);
      $worker->setExports($this->_exports);
      $worker->setInspector($this->_inspector);
      $worker->start();
    }
  }
}

<?php

namespace Boris;

/**
 * Boris is a tiny REPL for PHP.
 */
class Boris {
  private $_prompt;
  private $_historyFile;

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
  }

  /**
   * Start the REPL (display the readline prompt).
   *
   * This method never returns.
   */
  public function start() {
    if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $socks)) {
      throw new RuntimeException('Failed to create socket pair');
    }

    $pid = pcntl_fork();

    if ($pid > 0) {
      $client = new Boris_ReadlineClient($socks[1]);
      $client->start($this->_prompt, $this->_historyFile);
    } elseif ($pid < 0) {
      throw new RuntimeException('Failed to fork child process');
    } else {
      $worker = new Boris_EvalWorker($socks[0]);
      $worker->start();
    }
  }
}

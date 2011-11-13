<?php

/**
 * The Readline client is what the user spends their time entering text into.
 *
 * Input is collected and sent to {@link Boris_EvalWorker} for processing.
 */
class Boris_ReadlineClient {
  private $_socket;
  private $_prompt;
  private $_historyFile;
  private $_clear = false;

  /**
   * Create a new ReadlineClient using $socket for communication.
   *
   * @param resource $socket
   */
  public function __construct($socket) {
    $this->_socket = $socket;
  }

  /**
   * Start the client with an prompt and readline history path.
   *
   * This method never returns.
   *
   * @param string $prompt
   * @param string $historyFile
   */
  public function start($prompt, $historyFile) {
    readline_read_history($historyFile);

    declare(ticks = 1);
    pcntl_signal(SIGCHLD, SIG_IGN);
    // the following works, but the socket seems to be dead after ctrl-c
    //pcntl_signal(SIGINT, array($this, 'clear'));

    $parser = new Boris_ShallowParser();
    $buf = '';

    for (;;) {
      $this->_clear = false;
      $line = readline($buf == '' ? $prompt : str_pad('*> ', strlen($prompt), ' ', STR_PAD_LEFT));

      if ($this->_clear) {
        $buf = '';
        continue;
      }

      if (false === $line) {
        $buf = 'exit(0);'; // ctrl-d acts like exit
      }

      if (strlen($line) > 0) {
        readline_add_history($line);
      }

      $buf .= sprintf("%s\n", $line);

      if ($statements = $parser->statements($buf)) {
        $buf = '';
        foreach ($statements as $stmt) {
          if (false === $written = socket_write($this->_socket, $stmt)) {
            throw new RuntimeException('Socket error: failed to write data');
          }

          if ($written > 0) {
            $status = socket_read($this->_socket, 1);
            if ($status == Boris_EvalWorker::EXITED) {
              readline_write_history($historyFile);
              echo "\n";
              exit(0);
            }
          }
        }
      }
    }
  }

  public function clear() {
    $this->_clear = true;
  }
}

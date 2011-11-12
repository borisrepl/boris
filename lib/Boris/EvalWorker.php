<?php

/**
 * EvalWorker is reponsible for evaluating PHP expressions in forked processes.
 */
class Boris_EvalWorker {
  const ABNORMAL_EXIT = 65280;
  const DONE   = "\0";
  const EXITED = "\1";

  private $_socket;
  private $_ppid;
  private $_pid;

  /**
   * Create a new worker using the given socket for communication.
   *
   * @param resource $socket
   */
  public function __construct($socket) {
    $this->_socket = $socket;
  }

  /**
   * Start the worker.
   *
   * This method never returns.
   */
  public function start() {
    /* Note the naming of the local variables due to shared scope with the user here */
    for (;;) {
      $__input = '';
      while ('' !== $__buf = socket_read($this->_socket, 8192, PHP_BINARY_READ)) {
        $__input .= $__buf;
        if (strlen($__buf) < 8192) {
          break;
        }
      }

      $this->_ppid = posix_getpid();
      $this->_pid  = pcntl_fork();

      if ($this->_pid < 0) {
        throw new RuntimeException('Failed to fork child labourer');
      } elseif ($this->_pid > 0) {
        pcntl_waitpid($this->_pid, $__status);

        if ($__status != self::ABNORMAL_EXIT) {
          if (!socket_write($this->_socket, self::EXITED)) {
            throw new RuntimeException('Socket error: failed to write data');
          }
          exit(0);
        }
      } else {
        var_dump(eval($__input));
        $this->_expungeOldWorker();
      }

      if (!socket_write($this->_socket, self::DONE)) {
        throw new RuntimeException('Socket error: failed to write data');
      }
    }
  }

  // -- Private Methods

  private function _expungeOldWorker() {
    posix_kill($this->_ppid, SIGTERM);
    pcntl_signal_dispatch();
  }
}

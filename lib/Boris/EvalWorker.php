<?php

namespace Boris;

/**
 * EvalWorker is reponsible for evaluating PHP expressions in forked processes.
 */
class EvalWorker {
  const ABNORMAL_EXIT = 65280;
  const DONE   = "\0";
  const EXITED = "\1";
  const FAILED = "\2";

  private $_socket;
  private $_exports;
  private $_ppid;
  private $_pid;

  /**
   * Create a new worker using the given socket for communication.
   *
   * @param resource $socket
   * @param array $exports variables to export to the eval scope
   */
  public function __construct($socket, $exports=array()) {
    $this->_socket = $socket;
    $this->_exports = $exports;
  }

  /**
   * Start the worker.
   *
   * This method never returns.
   */
  public function start() {
    extract($this->_exports);

    /* Note the naming of the local variables due to shared scope with the user here */
    for (;;) {
      $__input = '';
      while ('' !== $__buf = socket_read($this->_socket, 8192, PHP_BINARY_READ)) {
        $__input .= $__buf;
        if (strlen($__buf) < 8192) {
          break;
        }
      }

      $__response = self::DONE;

      $this->_ppid = posix_getpid();
      $this->_pid  = pcntl_fork();

      if ($this->_pid < 0) {
        throw new \RuntimeException('Failed to fork child labourer');
      } elseif ($this->_pid > 0) {
        pcntl_waitpid($this->_pid, $__status);

        if ($__status != self::ABNORMAL_EXIT) {
          $__response = self::EXITED;
        } else {
          $__response = self::FAILED;
        }
      } else {
        $__pid = posix_getpid();

        $__result = eval($__input);

        if (posix_getpid() != $__pid) {
          // whatever the user entered caused a forked child
          // (totally valid, but we don't want that child to loop and wait for input)
          exit(0);
        }

        if (preg_match('/\s*return\b/i', $__input)) {
          echo " → ";
          var_dump($__result);
        }
        $this->_expungeOldWorker();
      }

      if (!socket_write($this->_socket, $__response)) {
        throw new \RuntimeException('Socket error: failed to write data');
      }

      if ($__response == self::EXITED) {
        exit(0);
      }
    }
  }

  // -- Private Methods

  private function _expungeOldWorker() {
    posix_kill($this->_ppid, SIGTERM);
    pcntl_signal_dispatch();
  }
}

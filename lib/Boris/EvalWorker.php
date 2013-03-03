<?php

namespace Boris;

/**
 * EvalWorker is responsible for evaluating PHP expressions in forked processes.
 */
class EvalWorker {
  const ABNORMAL_EXIT = 65280;
  const DONE   = "\0";
  const EXITED = "\1";
  const FAILED = "\2";

  private $_socket;
  private $_exports = array();
  private $_ppid;
  private $_pid;
  private $_cancelled;
  private $_inspector;

  /**
   * Create a new worker using the given socket for communication.
   *
   * @param resource $socket
   */
  public function __construct($socket) {
    $this->_socket    = $socket;
    $this->_inspector = new DumpInspector();
  }

  /**
   * Set local variables to be placed in the workers's scope.
   *
   * @param array $exports
   */
  public function setExports($exports) {
    $this->_exports = $exports;
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
   * Start the worker.
   *
   * This method never returns.
   */
  public function start() {
    extract($this->_exports);

    /* Note the naming of the local variables due to shared scope with the user here */
    for (;;) {
      declare(ticks = 1);
      // don't exit on ctrl-c
      pcntl_signal(SIGINT, SIG_IGN, true);

      $this->_cancelled = false;

      $__input = $this->_read($this->_socket);

      if ($__input === null) {
        continue;
      }

      $__response = self::DONE;

      $this->_ppid = posix_getpid();
      $this->_pid  = pcntl_fork();

      if ($this->_pid < 0) {
        throw new \RuntimeException('Failed to fork child labourer');
      } elseif ($this->_pid > 0) {
        // kill the child on ctrl-c
        pcntl_signal(SIGINT, array($this, 'cancelOperation'), true);
        pcntl_waitpid($this->_pid, $__status);

        if (!$this->_cancelled && $__status != self::ABNORMAL_EXIT) {
          $__response = self::EXITED;
        } else {
          $__response = self::FAILED;
        }
      } else {
        // undo ctrl-c signal handling ready for user code execution
        pcntl_signal(SIGINT, SIG_DFL, true);
        $__pid = posix_getpid();

        $__result = eval($__input);

        if (posix_getpid() != $__pid) {
          // whatever the user entered caused a forked child
          // (totally valid, but we don't want that child to loop and wait for input)
          exit(0);
        }

        if (preg_match('/\s*return\b/i', $__input)) {
					printf(" → %s\n", $this->_inspector->inspect($__result));
        }
        $this->_expungeOldWorker();
      }

      if (!fwrite($this->_socket, $__response)) {
        throw new \RuntimeException('Socket error: failed to write data');
      }

      if ($__response == self::EXITED) {
        exit(0);
      }
    }
  }

  /**
   * While a child process is running, terminate it immediately.
   */
  public function cancelOperation() {
    printf("Cancelling...\n");
    $this->_cancelled = true;
    posix_kill($this->_pid, SIGKILL);
    pcntl_signal_dispatch();
  }

  // -- Private Methods

  private function _expungeOldWorker() {
    posix_kill($this->_ppid, SIGTERM);
    pcntl_signal_dispatch();
  }

  private function _read($socket)
  {
    $read = array($socket);
    $write = null;
    $except = array($socket);

    if (stream_select($read, $write, $except, 10) > 0) {
      if ($read) {
        return stream_get_contents($read[0]);
      } else if ($except) {
        throw new \UnexpectedValueException("Socket error: closed");
      }
    }
  }
}

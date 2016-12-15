<?php

namespace Boris;

/**
 * EvalWorker is responsible for evaluating PHP expressions in forked processes.
 */
class EvalWorker
{
    const ABNORMAL_EXIT = 255;
    const DONE = "\0";
    const EXITED = "\1";
    const FAILED = "\2";
    const READY = "\3";

    const D_VARS       = 'vars';
    const D_VARS_TYPE  = 'type';
    const D_VARS_CLASS = 'class';
    const D_CLASSES    = 'classes';
    const D_FUNCTIONS  = 'functions';

    const INTERNAL_VARS = [
        'baseVars'   => true,
        '__scope'    => true,
        'hooks'      => true,
        '__hook'     => true,
        '__input'    => true,
        '__response' => true,
        '__oldexh'   => true,
        '__pid'      => true,
        '__result'   => true,
        '__hasError' => true,
    ];
    
    private $_socket;
    private $_exports = array();
    private $_startHooks = array();
    private $_failureHooks = array();
    private $_ppid;
    private $_pid;
    private $_cancelled;
    private $_inspector;
    private $_userExceptionHandler;

    /**
     * Create a new worker using the given socket for communication.
     *
     * @param resource $socket
     */
    public function __construct($socket)
    {
        $this->_socket    = $socket;
        $this->_inspector = new DumpInspector();
        stream_set_blocking($socket, 0);
    }
    
    /**
     * Set local variables to be placed in the workers's scope.
     *
     * @param array|string $local
     * @param mixed $value, if $local is a string
     */
    public function setLocal($local, $value = null)
    {
        if (!is_array($local)) {
            $local = array(
                $local => $value
            );
        }
        
        $this->_exports = array_merge($this->_exports, $local);
    }
    
    /**
     * Set hooks to run inside the worker before it starts looping.
     *
     * @param array $hooks
     */
    public function setStartHooks($hooks)
    {
        $this->_startHooks = $hooks;
    }
    
    /**
     * Set hooks to run inside the worker after a fatal error is caught.
     *
     * @param array $hooks
     */
    public function setFailureHooks($hooks)
    {
        $this->_failureHooks = $hooks;
    }
    
    /**
     * Set an Inspector object for Boris to output return values with.
     *
     * @param object $inspector any object the responds to inspect($v)
     */
    public function setInspector($inspector)
    {
        $this->_inspector = $inspector;
    }
    
    /**
     * Start the worker.
     *
     * This method never returns.
     */
    public function start()
    {
        $baseVars = get_defined_vars();

        $__scope = $this->_runHooks($this->_startHooks);
        extract($__scope);
        
        $this->_write($this->_socket, self::READY);
        $this->_sendDeclaredStuff($this->_socket, $baseVars, get_defined_vars());
        /* Note the naming of the local variables due to shared scope with the user here */
        for (;;) {
            declare (ticks = 1);
            // don't exit on ctrl-c
            pcntl_signal(SIGINT, SIG_IGN, true);
            
            $this->_cancelled = false;
            
            $__input = $this->_transform($this->_read($this->_socket));
            
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
                pcntl_signal(SIGINT, array(
                    $this,
                    'cancelOperation'
                ), true);
                pcntl_waitpid($this->_pid, $__status);
                
                if (!$this->_cancelled && $__status != (self::ABNORMAL_EXIT << 8)) {
                    $__response = self::EXITED;
                } else {
                    $this->_runHooks($this->_failureHooks);
                    $__response = self::FAILED;
                }
            } else {
                // if the user has installed a custom exception handler, install a new
                // one which calls it and then (if the custom handler didn't already exit)
                // exits with the correct status.
                // If not, leave the exception handler unset; we'll display
                // an uncaught exception error and carry on.
                $__oldexh = set_exception_handler(array(
                    $this,
                    'delegateExceptionHandler'
                ));
                if ($__oldexh && !$this->_userExceptionHandler) {
                    $this->_userExceptionHandler = $__oldexh; // save the old handler (once)
                } else {
                    restore_exception_handler();
                }
                
                // undo ctrl-c signal handling ready for user code execution
                pcntl_signal(SIGINT, SIG_DFL, true);
                $__pid = posix_getpid();

                $__result = null;
                $__hasError = false;
                try {
                    $__result = eval($__input);
                } catch(\Throwable $t) {
                    $__hasError = true;
                    while($t) {
                        fwrite(STDERR,  $t->getMessage().PHP_EOL);
                        fwrite(STDERR,  $t->getTraceAsString().PHP_EOL);
                        $t = $t->getPrevious();
                    }
                }
                
                if (posix_getpid() != $__pid) {
                    // whatever the user entered caused a forked child
                    // (totally valid, but we don't want that child to loop and wait for input)
                    exit(0);
                }
                
                if (!$__hasError && preg_match('/\s*return\b/i', $__input)) {
                    fwrite(STDOUT, sprintf("%s\n", $this->_inspector->inspect($__result)));
                }
                $this->_expungeOldWorker();
            }
            
            $this->_write($this->_socket, $__response);
            
            if ($__response == self::EXITED) {
                exit(0);
            }
            if($__response == self::DONE) {
                $this->_sendDeclaredStuff($this->_socket, $baseVars, get_defined_vars());
            }

        }
    }
    
    /**
     * While a child process is running, terminate it immediately.
     */
    public function cancelOperation()
    {
        printf("Cancelling...\n");
        $this->_cancelled = true;
        posix_kill($this->_pid, SIGKILL);
        pcntl_signal_dispatch();
    }
    
    /**
     * Call the user-defined exception handler, then exit correctly.
     */
    public function delegateExceptionHandler($ex)
    {
        call_user_func($this->_userExceptionHandler, $ex);
        exit(self::ABNORMAL_EXIT);
    }
    
    // -- Private Methods
    
    private function _runHooks($hooks)
    {
        extract($this->_exports);
        
        foreach ($hooks as $__hook) {
            if (is_string($__hook)) {
                eval($__hook);
            } elseif (is_callable($__hook)) {
                call_user_func($__hook, $this, get_defined_vars());
            } else {
                throw new \RuntimeException(sprintf('Hooks must be closures or strings of PHP code. Got [%s].', gettype($__hook)));
            }
            
            // hooks may set locals
            extract($this->_exports);
        }
        
        return get_defined_vars();
    }
    
    private function _expungeOldWorker()
    {
        posix_kill($this->_ppid, SIGTERM);
        pcntl_signal_dispatch();
    }
    
    private function _write($socket, $data)
    {
        if (!fwrite($socket, $data)) {
            throw new \RuntimeException('Socket error: failed to write data');
        }
    }
    
    private function _read($socket)
    {
        $read   = array(
            $socket
        );
        $except = array(
            $socket
        );
        
        if ($this->_select($read, $except) > 0) {
            if ($read) {
                return stream_get_contents($read[0]);
            } else if ($except) {
                throw new \UnexpectedValueException("Socket error: closed");
            }
        }

        return null;
    }
    
    private function _select(&$read, &$except)
    {
        $write = null;
        set_error_handler(function()
        {
            return true;
        }, E_WARNING);
        $result = stream_select($read, $write, $except, 10);
        restore_error_handler();
        return $result;
    }
    
    private function _transform($input)
    {
        if ($input === null) {
            return null;
        }
        
        $transforms = array(
            'exit' => 'exit(0)'
        );
        
        foreach ($transforms as $from => $to) {
            $input = preg_replace('/^\s*' . preg_quote($from, '/') . '\s*;?\s*$/', $to . ';', $input);
        }
        
        return $input;
    }

    private function _sendDeclaredStuff($socket, $baseVars, $get_defined_vars)
    {
        $declaredStuff = [
            self::D_VARS => [],
            self::D_CLASSES => []
        ];

        foreach(array_merge($GLOBALS, $get_defined_vars) as $name => $var) {

            if(isset($baseVars[$name]) || isset(self::INTERNAL_VARS[$name])) continue;

            $type = gettype($var);

            $declaredStuff[self::D_VARS][$name] = [
                self::D_VARS_TYPE => $type
            ];
            if(is_object($var)) {
                $declaredStuff[self::D_VARS][$name][self::D_VARS_CLASS] = get_class($var);
            }
        }

        foreach(array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits()) as $cls) {
            $declaredStuff[self::D_CLASSES][$cls] = true;
        }

        $declaredStuff[self::D_FUNCTIONS] = get_defined_functions();

        $this->_write($socket, json_encode($declaredStuff)."\n");
    }
}

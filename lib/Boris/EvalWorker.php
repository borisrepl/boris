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

    const CMD_EVAL = "\0";
    const CMD_COMPLETE = "\1";


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

        $__scope = $this->_runHooks($this->_startHooks);
        extract($__scope);
        
        $this->_write($this->_socket, self::READY);
        /* Note the naming of the local variables due to shared scope with the user here */
        for (;;) {
            declare (ticks = 1);
            // don't exit on ctrl-c
            pcntl_signal(SIGINT, SIG_IGN, true);
            
            $this->_cancelled = false;
            
            list($cmd, $data) = $this->_read($this->_socket);

            if($cmd == self::CMD_COMPLETE) {
                $vars = array_filter(
                    get_defined_vars(),
                    function($var) {
                        return !isset(EvalWorker::INTERNAL_VARS[$var]);
                    },
                    ARRAY_FILTER_USE_KEY);
                $this->_write(
                    $this->_socket,
                    json_encode($this->_complete($vars, $data)));
                continue;
            } else if($cmd != self::CMD_EVAL) {
                //ignore unknown commands
                continue;
            }
            $__input = $this->_transform($data);
            
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
                $cmd = stream_get_contents($read[0], 1);
                return [$cmd, stream_get_contents($read[0])];
            } else if ($except) {
                throw new \UnexpectedValueException("Socket error: closed");
            }
        }

        return [null, null];
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

    private function _complete($declaredStuff, $input) {
        // Accessing a class method or property
        if(preg_match('/\$([a-zA-Z0-9_]+)\->[a-zA-Z0-9_]*$/', $input, $m)) {
            $var = $m[1];
            $varValue = null;

            if(isset($declaredStuff[$var])) {
                $varValue = $declaredStuff[$var];
            } else if(isset($GLOBALS[$var])) {
                $varValue = $GLOBALS[$var];
            }

            if($varValue !== null && is_object($varValue)) {
                $refl = new \ReflectionClass($varValue);
                $methods = $refl->getMethods(\ReflectionMethod::IS_PUBLIC);
                foreach($methods as $method) {
                    if($method->name != '__construct') {
                        $return[] = $method->name . '(';
                    }
                }
                $properties = $refl->getProperties(\ReflectionProperty::IS_PUBLIC);
                foreach($properties as $property) {
                    $return[] = $property->name;
                }
            }
        } // Are we trying to auto complete a static class method, constant or property?
        else if(preg_match('/\$?([a-zA-Z0-9_\\\\]+)::(\$?)([a-zA-Z0-9_])*$/', $input, $m)) {
            $class = $m[1];
            $refl = null;

            if(class_exists($class)) {
                $refl = new \ReflectionClass($class);
            } else if(isset($GLOBALS[$class]) && is_object($GLOBALS[$class])) {
                $refl = new \ReflectionClass($GLOBALS[$class]);
            }
            if(!is_null($refl)) {
                $exploded = explode('\\', $class);
                $class = array_pop($exploded);
                if(empty($m[2])) {
                    $methods = $refl->getMethods(\ReflectionMethod::IS_STATIC);
                    foreach($methods as $method) {
                        if($method->isPublic()) {
                            $return[] = $class . '::' . $method->name . '(';
                        }
                    }
                    $constants = $refl->getConstants();
                    foreach($constants as $constant => $value) {
                        $return[] = $class . '::' . $constant;
                    }
                    $return[] = $class.'::class';
                }
                if(!empty($m[2]) || empty($m[3])) {
                    $properties = $refl->getProperties(\ReflectionProperty::IS_STATIC);
                    foreach($properties as $property) {
                        if($property->isPublic()) {
                            $return[] = $class . '::$' . $property->name;
                        }
                    }
                }
            }
        } else if(preg_match('/\\\\([a-zA-Z0-9_\\\\]+)$/', $input, $m)) {
            $match = $m[1];
            $exploded = explode('\\', $match);
            $lastComponentIndex = count($exploded) - 1;
            if($lastComponentIndex == -1) {
                return false;
            }
            $comp = [];
            foreach(get_declared_classes() as $cl) {
                if(strncmp($match, $cl, strlen($match)) == 0) {
                    $clExploded = explode('\\', $cl);
                    array_splice($clExploded, 0, $lastComponentIndex);
                    $comp[implode('\\', $clExploded)] = true;
                }
            }

            $return = array_keys($comp);

        } else if(preg_match('/\'[^\']*$/', $input) || preg_match('/"[^"]*$/', $input)) {
            return false; // This makes readline auto-complete files
        } else if(preg_match('/\$[a-zA-Z0-9_]*$/', $input)) {
            $return = array_merge(array_keys($declaredStuff), array_keys($GLOBALS));
        } else {
            $functions = get_defined_functions();
            $classes = get_declared_classes();
            $functions['internal'] = array_map(function($v) {
                return $v . '(';
            }, $functions['internal']);

            $functions['user'] = array_map(function($v) {
                return $v . '(';
            }, $functions['user']);
            $return = array_merge($classes, $functions['user'], $functions['internal'], array('require ', 'echo '));
        }
        if(empty($return)) {
            return array('');
        }
        return $return;
    }
}

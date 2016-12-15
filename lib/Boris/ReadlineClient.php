<?php

namespace Boris;

/**
 * The Readline client is what the user spends their time entering text into.
 *
 * Input is collected and sent to {@link \Boris\EvalWorker} for processing.
 */
class ReadlineClient
{
    private $_socket;
    private $_prompt;
    private $_historyFile;
    private $_clear = false;

    private $_vars = [
        EvalWorker::D_VARS      => [],
        EvalWorker::D_CLASSES   => [],
        EvalWorker::D_FUNCTIONS => [
            'internal' => [],
            'user'     => []
        ]
    ];
    
    /**
     * Create a new ReadlineClient using $socket for communication.
     *
     * @param resource $socket
     */
    public function __construct($socket)
    {
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
    public function start($prompt, $historyFile)
    {
        readline_read_history($historyFile);
        
        declare (ticks = 1);
        pcntl_signal(SIGCHLD, SIG_IGN);
        pcntl_signal(SIGINT, array(
            $this,
            'clear'
        ), true);

        readline_completion_function($this->_getReadlineCompletionFunction($this->_vars));
        
        // wait for the worker to finish executing hooks
        if (fread($this->_socket, 1) != EvalWorker::READY) {
            throw new \RuntimeException('EvalWorker failed to start');
        }

        $this->_readVars();
        
        $parser = new ShallowParser();
        $buf    = '';
        $lineno = 1;
        
        for (;;) {
            $this->_clear = false;
            $line         = readline(sprintf('[%d] %s', $lineno, ($buf == '' ? $prompt : str_pad('*> ', strlen($prompt), ' ', STR_PAD_LEFT))));

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
                ++$lineno;
                
                $buf = '';
                foreach ($statements as $stmt) {
                    if (false === $written = fwrite($this->_socket, $stmt)) {
                        throw new \RuntimeException('Socket error: failed to write data');
                    }
                    
                    if ($written > 0) {
                        $status = fread($this->_socket, 1);
                        if ($status == EvalWorker::EXITED) {
                            readline_write_history($historyFile);
                            echo "\n";
                            exit(0);
                        } elseif ($status == EvalWorker::FAILED) {
                            break;
                        } elseif($status == EvalWorker::DONE) {
                            $this->_readVars();
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Clear the input buffer.
     */
    public function clear()
    {
        // FIXME: I'd love to have this send \r to readline so it puts the user on a blank line
        $this->_clear = true;
    }

    private function _readVars() {
        $vars = "";
        while(true) {
            $tmp = fread($this->_socket, 1);
            if($tmp === false || $tmp == "\n") {
                break;
            }
            $vars .= $tmp;
        }
        $this->_vars = json_decode($vars, true);
    }


    private function _getReadlineCompletionFunction(&$local) {
        return function() use (&$local) {
            // Get the full input buffer so we can use some context when suggesting things.
            $info = readline_info();
            $input = substr($info['line_buffer'], 0, $info['end']);
            $return = array();
            // Accessing a class method or property
            if(preg_match('/\$([a-zA-Z0-9_]+)\->[a-zA-Z0-9_]*$/', $input, $m)) {
                $var = $m[1];
                $varValue = null;

                if(isset($local[EvalWorker::D_VARS][$var])) {
                    $varValue = $local[EvalWorker::D_VARS][$var];
                }

                if($varValue !== null && $varValue[EvalWorker::D_VARS_TYPE] == 'object') {
                    $refl = new \ReflectionClass($varValue[EvalWorker::D_VARS_CLASS]);
                    $methods = $refl->getMethods(\ReflectionMethod::IS_PUBLIC);
                    foreach($methods as $method) {
                        $return[] = $method->name . '(';
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
                foreach(array_keys($this->_vars[EvalWorker::D_CLASSES]) as $cl) {
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
                $return = array_keys($local[EvalWorker::D_VARS]);
            } else {
                $functions = $local[EvalWorker::D_FUNCTIONS];
                $classes = $local[EvalWorker::D_CLASSES];
                $functions['internal'] = array_map(function($v) {
                    return $v . '(';
                }, $functions['internal']);

                $functions['user'] = array_map(function($v) {
                    return $v . '(';
                }, $functions['user']);
                $return = array_merge($return, $classes, $functions['user'], $functions['internal'], array('require ', 'echo '));
            }
            if(empty($return)) {
                return array('');
            }
            return $return;
        };
    }
}

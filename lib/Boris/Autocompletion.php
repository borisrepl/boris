<?php

/* vim: set shiftwidth=2 expandtab softtabstop=2: */

namespace Boris;

class Autocompletion {
    private $symbols = [];

    public function __construct() {
    }

    public function complete($status)
    {
        $chunk = substr($status['line'], 0, $status['cursor']);

        /**
         * Verify if we are in a variable context
         * Take the last word and check if the first character is a '$'
         */
        $tokens = preg_split('/[^a-zA-Z_\$]/', $chunk);
        $current = end($tokens);
        if($current && $current[0] == '$') {
            return $this->getVariables();
        } else {
            return $this->getSymbols();
        }
    }

    private function getSymbols() {
        if(empty($this->symbols)) {
            $this->symbols = call_user_func_array(
                "array_merge", array_values(get_defined_functions())
            );

            $this->symbols = array_merge(
                $this->symbols, array_keys(get_defined_constants())
            );
        }

        return $this->symbols;
    }

    private function getVariables() {
        /**
         * TODO: Find a way to access EvalWorker context/scope to access vars
         */
        return array('this', 'container');
    }

}

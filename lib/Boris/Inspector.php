<?php

namespace Boris;

/**
 * Something that is capable of returning a useful representation of a variable.
 */
interface Inspector
{
    /**
     * Return a debug-friendly string representation of $variable.
     *
     * @param mixed $variable
     *
     * @return string
     */
    public function inspect($variable);
}

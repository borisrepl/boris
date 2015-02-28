<?php

namespace Boris;

/**
 * Passes values through var_export() to inspect them.
 */
class ExportInspector implements Inspector
{
    public function inspect($variable)
    {
        return sprintf(" → %s", var_export($variable, true));
    }
}

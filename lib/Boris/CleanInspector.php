<?php

/* vim: set shiftwidth=2 expandtab softtabstop=2: */

namespace Boris;

/**
 * Handles common return types more elegantly than var_dump,
 * enables console color highlighting by type.
 */
class CleanInspector implements Inspector {
  
  // Terminal color codes
  static $TERM_COLORS = array(
    'black' =>     "\033[0;30m",
    'default' =>   "\033[1;30m",
    'dk_grey' =>   "\033[1;30m",
    'dk_red' =>    "\033[0;31m",
    'lt_red' =>    "\033[1;31m",
    'dk_green' =>  "\033[0;32m",
    'lt_green' =>  "\033[1;32m",
    'dk_yellow' => "\033[0;33m",
    'lt_yellow' => "\033[1;33m",
    'dk_blue' =>   "\033[0;34m",
    'lt_blue' =>   "\033[1;34m",
    'dk_purple' => "\033[0;35m",
    'lt_purple' => "\033[1;35m",
    'dk_cyan' =>   "\033[0;36m",
    'lt_cyan' =>   "\033[1;36m",
    'lt_gray' =>   "\033[0;37m",
    'lt_grey' =>   "\033[0;37m",
    'white' =>     "\033[1;37m"
  );
  
  /**
   * If you'd prefer to skip the color highlighting, pass
   * false on construction.  If you'd like to change which
   * color is associated with a given type, pass in an associative
   * array with the keys 'int', 'string', 'bool', and 'default' and
   * values from the TERM_COLORS hash.
   */
  public function __construct($colorMap = null) {
    // Select a color map to use from passed param, or use default
    if (is_array($colorMap) || $colorMap === false) {
      $this->colorMap = $colorMap;
    } else {
      $this->colorMap = array(
        'num' => 'lt_blue',
        'string' => 'lt_cyan',
        'bool' => 'lt_purple',
        'default' => 'default'
      );
    }
  }
  
  /**
   * Implement the inspector interface
   */
  public function inspect($variable) {
    // Holds a list of all the complex items we've seen, to aid in
    // preventing infinite recursion
    $this->seenList = array();
    
    // Dump the variable, with initial indent to match "->"
    return $this->dump($variable, '   ');
  }
  
  /**
   * Meat'n'bones of the system, conditionally colors value
   * based on type, supports nested indents for object and
   * array output.
   */
  protected function dump($val, $indent = '') {
    if (is_string($val)) {
      $text = $this->color('string', var_export($val, true));
      
    } else if (is_bool($val)) {
      $text = $this->color('bool', var_export($val, true));

    } else if (is_numeric($val)) {
      $text = $this->color('num', var_export($val, true));
      
    } else if (is_array($val)) {
      // Get array properties - length and type (indexed or mapped)
      $indexed = array_values($val) === $val;
      if (count($val) > 10) {
        $extraCount = count($val) - 10;
        $val = array_slice($val, 0, 10, true);
      } else {
        $extraCount = 0;
      }
      
      if ($indexed) {
        // Indexed array, show simple list output
        if (empty($val)) {
          // Empty array
          $text = $this->color('default', "[]");
          
        } else if ($this->isSeen($val)) {
          // Array we've seen before!
          $text = $this->color('default', "[ ... recursion ... ]");
          
        } else {
          // The real deal, display with contents
          $text = $this->color('default', "[\n");
          $counter = count($val);
          foreach ($val as $v) {
            $text .= $indent . "  " . $this->dump($v, $indent . '  ');
            if (--$counter || $extraCount) { $text .= $this->color('default', ','); }
            $text .= "\n";
          }
          if ($extraCount) {
            $text .= $this->color('default', $indent . "  ... and $extraCount more ...\n");
          }
          $text .= $indent . $this->color('default', ']');
        }
        
      } else {
        // Mapped array, show key => value output
        if (empty($val)) {
          // Empty case
          $text = $this->color('default', "{}");
            
        } else if ($this->isSeen($val)) {
          // Array we've seen before!
          $text = $this->color('default', "{ ... recursion ... }");

        } else {
          // Full array, show contents
          $text = $this->color('default', "{\n");
          $counter = count($val);
          foreach ($val as $k => $v) {
            $text .= $indent . "  " . $this->dump($k) . $this->color('default', ' => ') . $this->dump($v, $indent . '  ');
            if (--$counter || $extraCount) { $text .= $this->color('default', ','); }
            $text .= "\n";
          }
          if ($extraCount) {
            $text .= $indent . $this->color('default', "  ... and $extraCount more ...\n");
          }
          $text .= $indent . $this->color('default', '}');
        }
      }
      
    } else if (is_object($val)) {
      $text = $this->color('default', get_class($val));
      if ($this->isSeen($val)) {
        // Don't recurse infinitely, please
        $text .=  $this->color('default', " { ... recursion ... }");
        
      } else {
        // Show full contents
        $text .= $this->color('default', " {\n");
        $vars = get_object_vars($val);
        $keys = array_keys($vars);
        sort($keys);
        $counter = count($vars);
        foreach ($keys as $k) {
          $v = $vars[$k];
          $text .= $indent . '  ' . $this->color('default', $k . ' => ') . $this->dump($v, $indent . '  ');
          if (--$counter) { $text .= $this->color('default', ','); }
          $text .= "\n";
        }
        $text .= $indent . $this->color('default', '}');
      }

    } else {
      // Fall back on var_dump for, eg, functions
      ob_start();
      var_dump($val);
      $text = trim(ob_get_clean());
    }    

    return $text;
  }

  /**
   * Colors passed string based on terminal color lookup, resetting to
   * default color on return.
   */
  protected function color($type, $str) {
    if ($this->colorMap) {
      $index = $this->colorMap[$type];
      if (!$index) { $index = $this->colorMap['default']; }
      return static::$TERM_COLORS[$index] . $str . "\033[0m";
    } else {
      return $str;
    }
  }
  
  /**
   * Call with each object to be output, to see if it has already been output,
   * for use in recursion stack blow prevention.  :-)
   */
  protected function isSeen($obj) {
    if (!$obj) { return false; }
    foreach ($this->seenList as $seen) {
      // Test (using instance equality operator) for object identity match
      // with existing objects
      if ($obj === $seen) { return true; }
    }
    
    // If we get here, the object has not been seen, so add it to the list!
    $this->seenList[] = $obj;
    return false;
  }
  
} 

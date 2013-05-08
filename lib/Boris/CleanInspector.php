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
    'dk_gray' =>   "\033[1;30m",
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
   * false on construction.
   */
  public function __construct($useColors = true) {
    $this->useColors = $useColors;
  }
  
  /**
   * Implement the inspector interface
   */
  public function inspect($variable) {
    return $this->dump($variable, '   ');
  }
  
  /**
   * Meat'n'bones of the system, conditionally colors value
   * based on type, supports nested indents for object and
   * array output.
   */
  protected function dump($val, $indent = '') {
    if (is_string($val)) {
      $text = $this->color('lt_cyan', var_export($val, true));
      
    } else if (is_bool($val)) {
      $text = $this->color('lt_purple', var_export($val, true));

    } else if (is_numeric($val)) {
      $text = $this->color('lt_blue', var_export($val, true));
      
    } else if (is_array($val)) {
      $indexed = array_values($val) === $val;
      if (count($val) > 10) {
        $extraCount = count($val) - 10;
        $val = array_slice($val, 0, 10, true);
      } else {
        $extraCount = 0;
      }
      
      if ($indexed) {
        if (empty($val)) {
          $text = $this->color('dk_gray', "[]");
        } else {
          $text = $this->color('dk_gray', "[\n");
          $counter = count($val);
          foreach ($val as $v) {
            $text .= $indent . "  " . $this->dump($v, $indent . '  ');
            if (--$counter || $extraCount) { $text .= $this->color('dk_gray', ','); }
            $text .= "\n";
          }
          if ($extraCount) {
            $text .= $this->color('dk_gray', $indent . "  ... and $extraCount more ...\n");
          }
          $text .= $indent . $this->color('dk_gray', ']');
        }
        
      } else {
        if (empty($val)) {
          $text = $this->color('dk_gray', "{}");
        } else {
          $text = $this->color('dk_gray', "{\n");
          $counter = count($val);
          foreach ($val as $k => $v) {
            $text .= $indent . "  " . $this->dump($k) . $this->color('dk_gray', ' => ') . $this->dump($v, $indent . '  ');
            if (--$counter || $extraCount) { $text .= $this->color('dk_gray', ','); }
            $text .= "\n";
          }
          if ($extraCount) {
            $text .= $indent . $this->color('dk_gray', "  ... and $extraCount more ...\n");
          }
          $text .= $indent . $this->color('dk_gray', '}');
        }
      }
      
    } else if (is_object($val)) {
      $text = $this->color('dk_gray', get_class($val) . " {\n");
      $vars = get_object_vars($val);
      $keys = array_keys($vars);
      sort($keys);
      $counter = count($vars);
      foreach ($keys as $k) {
        $v = $vars[$k];
        $text .= $indent . '  ' . $this->color('dk_gray', $k . ' => ') . $this->dump($v, $indent . '  ');
        if (--$counter) { $text .= $this->color('dk_gray', ','); }
        $text .= "\n";
      }
      $text .= $indent . $this->color('dk_gray', '}');

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
  protected function color($col, $str) {
    if ($this->useColors) {
      return static::$TERM_COLORS[$col] . $str . "\033[0m";
    } else {
      return $str;
    }
  }
  
} 

<?php

/* vim: set shiftwidth=2 expandtab softtabstop=2: */

/**
 * @author Rob Morris <rob@irongaze.com>
 * @author Chris Corbyn <chris@w3style.co.uk>
 *
 * Copyright © 2013 Rob Morris.
 */

namespace Boris;

/**
 * Identifies data types in data structures and syntax highlights them.
 */
class ColoredInspector implements Inspector {
  // FIXME: Clean this up
  static $TERM_COLORS = array(
    'black'        => "\033[0;30m",
    'white'        => "\033[1;37m",
    'none'         => "\033[1;30m",
    'dark_grey'    => "\033[1;30m",
    'light_grey'   => "\033[0;37m",
    'dark_red'     => "\033[0;31m",
    'light_red'    => "\033[1;31m",
    'dark_green'   => "\033[0;32m",
    'light_green'  => "\033[1;32m",
    'dark_yellow'  => "\033[0;33m",
    'light_yellow' => "\033[1;33m",
    'dark_blue'    => "\033[0;34m",
    'light_blue'   => "\033[1;34m",
    'dark_purple'  => "\033[0;35m",
    'light_purple' => "\033[1;35m",
    'dark_cyan'    => "\033[0;36m",
    'light_cyan'   => "\033[1;36m",
  );

  private $_fallback;
  private $_colorMap = array();

  /**
   * Initialize a new ColoredInspector, using $colorMap.
   *
   * The colors should be an associative array with the keys:
   *
   *   - 'integer'
   *   - 'float'
   *   - 'keyword'
   *   - 'string'
   *   - 'boolean'
   *   - 'default'
   *
   * And the values, one of the following colors:
   *
   *   - 'none'
   *   - 'black'
   *   - 'white'
   *   - 'dark_grey'
   *   - 'light_grey'
   *   - 'dark_red'
   *   - 'light_red'
   *   - 'dark_green'
   *   - 'light_green'
   *   - 'dark_yellow'
   *   - 'light_yellow'
   *   - 'dark_blue'
   *   - 'light_blue'
   *   - 'dark_purple'
   *   - 'light_purple'
   *   - 'dark_cyan'
   *   - 'light_cyan'
   *
   * An empty $colorMap array effectively means 'none' for all types.
   *
   * @param array $colorMap
   */
  public function __construct($colorMap = null) {
    $this->_fallback = new DumpInspector();

    if (isset($colorMap)) {
      $this->_colorMap = $colorMap;
    } else {
      $this->_colorMap = $this->_defaultColorMap();
    }
  }

  public function inspect($variable) {
    return $this->_dump($variable);
  }

  // -- Private Methods

  private function _dump($value, $indent = 0, $seen = array()) {
    if (is_object($value) || is_array($value)) {
      if ($this->_isSeen($value, $seen)) {
        return $this->_colorize('default', '*** RECURSION ***', $indent);
      } else {
        $nextSeen = array_merge($seen, array($value));
      }
    }

    $tests = array(
      'is_null'    => '_dumpNull',
      'is_string'  => '_dumpString',
      'is_bool'    => '_dumpBoolean',
      'is_integer' => '_dumpInteger',
      'is_float'   => '_dumpFloat',
      'is_array'   => '_dumpArray',
      'is_object'  => '_dumpObject'
    );

    foreach ($tests as $predicate => $outputMethod) {
      if (call_user_func($predicate, $value))
        return call_user_func(
          array($this, $outputMethod),
          $value,
          $indent,
          $nextSeen
        );
    }

    return $this->_fallback->inspect($value);
  }

  private function _dumpNull($value, $indent, $seen) {
    return $this->_colorize('keyword', 'NULL', $indent);
  }

  private function _dumpString($value, $indent, $seen) {
    return $this->_colorize('string', var_export($value, true), $indent);
  }

  private function _dumpBoolean($value, $indent, $seen) {
    return $this->_colorize('bool', var_export($value, true), $indent);
  }

  private function _dumpInteger($value, $indent, $seen) {
    return $this->_colorize('integer', var_export($value, true), $indent);
  }

  private function _dumpFloat($value, $indent, $seen) {
    return $this->_colorize('float', var_export($value, true), $indent);
  }

  private function _dumpArray($value, $indent, $seen) {
    return $this->_dumpStructure('array', $value, $indent, $seen);
  }

  private function _dumpObject($value, $indent, $seen) {
    return $this->_dumpStructure(
      sprintf('object(%s)', get_class($value)),
      $value,
      $indent,
      $seen
    );
  }

  // FIXME: A better algorithm would map the data back as an array, then visit each elem and output the tree.
  private function _dumpStructure($type, $value, $indent, $seen) {
    $text = sprintf("%s(\n", $this->_colorize('keyword', $type, $indent));

    foreach ($value as $k => $v) {
      $text .= sprintf(
        "%s => %s\n",
        $this->_dump($k, $indent + 1, $seen),
        $this->_dump($v, $indent + 1, $seen)
      );
    }

    return sprintf('%s%s)', $text, str_repeat(' ', $indent * 2));
  }

  private function _defaultColorMap() {
    return array(
      'integer' => 'light_green',
      'float'   => 'light_yellow',
      'string'  => 'light_cyan',
      'bool'    => 'light_purple',
      'keyword' => 'light_purple',
      'default' => 'none'
    );
  }

  private function _colorize($type, $value, $indent) {
    if (!empty($this->_colorMap[$type])) {
      $color = $this->_colorMap[$type];
    } else {
      $color = $this->_colorMap['default'];
    }

    return sprintf(
      "%s%s%s\033[0m",
      str_repeat(' ', $indent * 2),
      static::$TERM_COLORS[$color],
      $value
    );
  }

  private function _isSeen($value, $seen) {
    foreach ($seen as $v) {
      if ($v === $value)
        return true;
    }

    return false;
  }
}

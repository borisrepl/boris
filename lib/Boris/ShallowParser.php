<?php

/**
 * The ShallowParser takes whatever is currently buffered and chunks it into individual statements.
 */
class Boris_ShallowParser {
  private $_pairs = array(
    '('   => ')',
    '{'   => '}',
    '['   => ']',
    '"'   => '"',
    "'"   => "'",
    '//'  => "\n",
    '#'   => "\n",
    '/*'  => '*/',
    '<<<' => '_heredoc_special_case_'
  );

  private $_initials;

  public function __construct() {
    $this->_initials   = '/^(' . implode('|', array_map(array($this, 'quote'), array_keys($this->_pairs))) . ')/';
  }

  /**
   * Break the $buffer into chunks, with one for each highest-level construct possible.
   *
   * If the buffer is incomplete, returns an empty array.
   *
   * @param string $buffer
   *
   * @return array
   */
  public function statements($buffer) {
    $stmt       = '';
    $states     = array();
    $statements = array();

    // this looks scarier than it is (it's deliberately procedural)...
    while (strlen($buffer) > 0) {
      $state      = end($states);
      $terminator = $state ? '/^.*?' . preg_quote($this->_pairs[$state], '/') . '/s' : null;

      // FIXME: Refactor this heredoc/nowdoc handling... it has a lot in common with other strings
      if ($state == '<<<') {
        if (preg_match('/^([\'"]?)([a-z_][a-z0-9_]*)\\1/i', $buffer, $match)) {
          $docId = $match[2];
          $stmt .= $match[0];
          $buffer = substr($buffer, strlen($match[0]));

          if (preg_match('/^(.*?\n' . $docId . ');?\n/s', $buffer, $match)) {
            $stmt .= $match[1];
            $buffer = substr($buffer, strlen($match[1]));
            array_pop($states);
          } else {
            break;
          }
        } else {
          array_pop($states); // not actually here-doc
          continue;
        }
      }
      // escaped char
      elseif (($state == '"' || $state == "'") && preg_match('/^[^' . $state . ']*?\\\\./s', $buffer, $match)) {
        $stmt .= $match[0];
        $buffer = substr($buffer, strlen($match[0]));
      } elseif ($state == '"' || $state == "'" || $state == '//' || $state == '#' || $state == '/*') {
        if (preg_match($terminator, $buffer, $match)) {
          $stmt .= $match[0];
          $buffer = substr($buffer, strlen($match[0]));
          array_pop($states);
        } else {
          break;
        }
      } elseif (preg_match($this->_initials, $buffer, $match)) {
        $stmt .= $match[0];
        $buffer = substr($buffer, strlen($match[0]));
        $states[] = $match[0];
      } elseif (preg_match('/^\s+/', $buffer, $match)) {
        if (!empty($statements)) {
          $statements[] = array_pop($statements) . $match[0];
        } else {
          $stmt .= $match[0];
        }
        $buffer = substr($buffer, strlen($match[0]));
      } else {
        $chr = substr($buffer, 0, 1);
        $stmt .= $chr;
        $buffer = substr($buffer, 1);
        if ($state && $chr == $this->_pairs[$state]) {
          array_pop($states);
        }

        if (empty($states) && ($chr == ';' || $chr == '}')) {
          $statements[] = "$stmt";
          $stmt = '';
        }
      }
    }

    if (!empty($statements) && trim($stmt) === '' && strlen($buffer) == 0) {
      $statements   = $this->_combine($statements);
      $statements []= $this->_prepareDebugStmt(array_pop($statements));
      return $statements;
    }
  }

  public function quote($token) {
    return preg_quote($token, '/');
  }

  // -- Private Methods

  private function _combine($statements) {
    $combined = array();

    foreach ($statements as $scope) {
      if (preg_match('/^\s*(;|else\b|elseif\b|catch\b)/i', $scope)) {
        $combined[] = ((string) array_pop($combined)) . $scope;
      } else {
        $combined[] = $scope;
      }
    }

    return $combined;
  }

  private function _isReturnable($input) {
    $input = trim($input);
    return substr($input, -1) == ';' && !preg_match(
      '/^(' .
      'echo|print|exit|die|goto|global|include|include_one|require|require_once|list|' .
      'return|do|for|while|if|function|namespace|class|interface|abstract|switch|' .
      'declare|throw|try' .
      ')\b/i',
      $input
    );
  }

  private function _prepareDebugStmt($input) {
    if ($this->_isReturnable($input) && !preg_match('/\s*return/i', $input)) {
      $input = sprintf('return %s', $input);
    }

    return $input;
  }

}

<?php

/**
 * Boris: A tiny REPL for PHP.
 *
 * @author Chris Corbyn
 */

/*! Predicate to determine if the `return` keyword can be placed before the input */
function boris_is_returnable($input) {
  $input = trim($input);
  return substr($input, -1) == ';' && !preg_match(
    '/^(' .
    'echo|exit|die|goto|global|include|include_one|require|require_once|list|' .
    'return|do|for|while|if|function|namespace|class|interface|abstract|switch|' .
    'declare|throw|try' .
    ')\b/ix',
    $input
  );
}

/*! Quite simply places `return` before the input where possible */
function boris_prepare_debug_stmt($input) {
  if (boris_is_returnable($input) && !preg_match('/\s*return/i', $input)) {
    $input = sprintf('return %s', $input);
  }

  return $input;
}

/*! PCRE quote a lexical token */
function boris_quote($token) {
  return preg_quote($token, '/');
}

/*! Performs a shallow parse, extracting an array of full statements */
function boris_statements($buffer) {
  // TODO: Support heredoc
  $pairs = array(
    '('  => ')',
    '{'  => '}',
    '['  => ']',
    '"'  => '"',
    "'"  => "'",
    '//' => "\n",
    '#'  => "\n",
    '/*' => '*/'
  );

  $stmt       = '';
  $states     = array();
  $initials   = '/^(' . implode('|', array_map('boris_quote', array_keys($pairs))) . ')/';
  $statements = array();

  // this looks scarier than it is...
  while (strlen($buffer) > 0) {
    $state      = end($states);
    $terminator = $state ? '/^.*?' . preg_quote($pairs[$state]) . '/s' : null;

    // escaped char
    if (($state == '"' || $state == "'") && preg_match('/^[^' . $state . ']*?\\\\./s', $buffer, $match)) {
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
    } elseif (preg_match($initials, $buffer, $match)) {
      $stmt .= $match[0];
      $buffer = substr($buffer, strlen($match[0]));
      $states[] = $match[0];
    } else {
      $chr = substr($buffer, 0, 1);
      $stmt .= $chr;
      $buffer = substr($buffer, 1);
      if ($state && $chr == $pairs[$state]) {
        array_pop($states);
      }

      if (empty($states) && ($chr == ';' || $chr == '}')) {
        $statements[] = $stmt;
        $stmt = '';
      }
    }
  }

  if (trim($stmt) == '') {
    $statements[] = boris_prepare_debug_stmt(array_pop($statements));
    return $statements;
  }
}

/*! Invoked in a child process after forking; starts the REPL worker */
function boris_start_worker($boris_sock) {
  for (;;) {
    $boris_input = '';
    while ('' !== $boris_buf = socket_read($boris_sock, 8192, PHP_BINARY_READ)) {
      $boris_input .= $boris_buf;
      if (strlen($boris_buf) < 8192) {
        break;
      }
    }

    $boris_ppid = posix_getpid();
    $boris_pid  = pcntl_fork();

    if ($boris_pid < 0) {
      throw new RuntimeException('Failed to fork child labourer');
    } elseif ($boris_pid > 0) {
      pcntl_waitpid($boris_pid, $boris_status); // stick around in case child exits
    } else {
      var_dump(eval($boris_input));
      posix_kill($boris_ppid, SIGTERM);
      pcntl_signal_dispatch();
    }

    socket_write($boris_sock, "\0"); // notify main process we're done
  }
}

/*! Invoked in the main process after forking the REPL worker; accepts user input  */
function boris_start_repl($sock) {
  $buf = '';
  for (;;) {
    if (false === $line = readline($buf == '' ? 'boris> ' : '    *> ')) {
      echo "\n";
      exit(0); // ctrl-d
    }

    $buf .= sprintf("%s\n", $line);

    if ($statements = boris_statements($buf)) {
      $buf = '';
      foreach ($statements as $stmt) {
        if (!(socket_write($sock, $stmt) && socket_read($sock, 1))) {
          throw new RuntimeException('Socket error: failed to write data');
        }
      }
    }
  }
}

/*! Start the REPL and Readline client; never returns */
function boris_start() {
  if (!is_callable('pcntl_fork')) {
    throw new RuntimeException('The pcntl extension must be installed to use boris');
  }

  if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $socks)) {
    throw new RuntimeException('Failed to create socket pair');
  }

  $pid = pcntl_fork();

  if ($pid > 0) {
    boris_start_repl($socks[1]);
  } elseif ($pid < 0) {
    throw new RuntimeException('Failed to fork child process');
  } else {
    boris_start_worker($socks[0]);
  }
}

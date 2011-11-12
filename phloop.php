<?php

function phloop_is_returnable($input) {
  $input = trim($input);
  return substr($input, -1) == ';' && !preg_match(
    '/^(return|for|while|if|function|class|interface|abstract|switch|declare|throw|try)\b/i',
    $input
  );
}

function phloop_prepare_debug_stmt($input) {
  if (phloop_is_returnable($input) && !preg_match('/\s*return/i', $input)) {
    $input = sprintf('return %s', $input);
  }

  return $input;
}

function phloop_quote($token) {
  return preg_quote($token, '/');
}

/*! Performs a shallow parse, extracting an array of full statements */
function phloop_statements($buffer) {
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
  $initials   = '/^(' . implode('|', array_map('phloop_quote', array_keys($pairs))) . ')/';
  $statements = array();

  while (strlen($buffer) > 0) {
    if (!$state = end($states)) { // initial state
      if (preg_match('/^\s+/', $buffer, $match)) {
        $stmt .= $match[0];
        $buffer = substr($buffer, strlen($match[0]));
      } elseif (preg_match($initials, $buffer, $match)) {
        $stmt .= $match[0];
        $buffer = substr($buffer, strlen($match[0]));
        $states[] = $match[0];
      } else {
        $chr = substr($buffer, 0, 1);
        $stmt .= $chr;
        $buffer = substr($buffer, 1);
        if ($chr == ';') {
          $statements[] = $stmt;
          $stmt = '';
        }
      }
    } else {
      // escaped in-string char
      if (($state == '"' || $state == "'") && preg_match('/^[^' . $state . ']*?\\\\./s', $buffer, $match)) {
        $stmt .= $match[0];
        $buffer = substr($buffer, strlen($match[0]));
      } elseif (preg_match('/^.*?' . preg_quote($pairs[$state], '/') . '/s', $buffer, $match)) {
        $stmt .= $match[0];
        $buffer = substr($buffer, strlen($match[0]));
        array_pop($states);
        if ($state == '{' && empty($states)) {
          $statements[] = $stmt;
          $stmt = '';
        }
      } else {
        break; // unconsumed input
      }
    }
  }

  if (trim($stmt) == '') {
    $statements[] = phloop_prepare_debug_stmt(array_pop($statements));
    return $statements;
  }
}

function phloop_start_worker($phloop_sock) {
  for (;;) {
    $phloop_input = '';
    while ('' !== $phloop_buf = socket_read($phloop_sock, 8192, PHP_BINARY_READ)) {
      $phloop_input .= $phloop_buf;
      if (strlen($phloop_buf) < 8192) {
        break;
      }
    }

    $phloop_ppid = posix_getpid();
    $phloop_pid  = pcntl_fork();

    if ($phloop_pid < 0) {
      throw new RuntimeException('Failed to fork child labourer');
    } elseif ($phloop_pid > 0) {
      pcntl_waitpid($phloop_pid, $phloop_status);
    } else {
      var_dump(eval($phloop_input));
      posix_kill($phloop_ppid, SIGTERM);
      pcntl_signal_dispatch();
    }

    socket_write($phloop_sock, "\0");
  }
}

function phloop_start_repl($sock) {
  $buf = '';
  for (;;) {
    if (false === $line = readline($buf == '' ? 'phloop> ' : '     *> ')) {
      echo "\n";
      exit(0); // ctrl-d
    }

    $buf .= sprintf("%s\n", $line);

    if ($statements = phloop_statements($buf)) {
      $buf = '';
      foreach ($statements as $stmt) {
        if (!(socket_write($sock, $stmt) && socket_read($sock, 1))) {
          throw new RuntimeException('Socket error: failed to write data');
        }
      }
    }
  }
}

function phloop_start() {
  if (!is_callable('pcntl_fork')) {
    throw new RuntimeException('The pcntl extension must be installed to use phloop');
  }

  if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $socks)) {
    throw new RuntimeException('Failed to create socket pair');
  }

  $pid = pcntl_fork();

  if ($pid > 0) {
    phloop_start_repl($socks[1]);
  } elseif ($pid < 0) {
    throw new RuntimeException('Failed to fork child process');
  } else {
    phloop_start_worker($socks[0]);
  }
}

phloop_start();

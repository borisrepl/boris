<?php

function phloop_is_returnable($input) {
  $input = trim($input);
  return substr($input, -1) == ';' && !preg_match(
    '/^return|for|while|if|function|class|interface|abstract|switch|declare|throw|try/i',
    $input
  );
}

function phloop_prepare_debug_stmt($input) {
  if (phloop_is_returnable($input) && !preg_match('/\s*return/i', $input)) {
    $input = sprintf('return %s', $input);
  }

  return $input;
}

function phloop_is_complete_statement($input) {
  if (substr(trim($input), -1) != ';') {
    return false;
  } else {
    return true;
  }
}

function phloop_start() {
  if (!is_callable('pcntl_fork')) {
    throw new RuntimeException('The pcntl extension must be installed to use phloop');
  }

  /*
   Note that:
     Namespacing phloop variables is important in this scope.
     Maintaining the user's variables between REPL cycles is important.
   */

  $phloop_buffer = '';

  for(;;) {
    if (false === $phloop_line = readline($phloop_buffer == '' ? 'phloop> ' : '     *> ')) {
      echo "\n";
      exit(0); // ctrl-d
    }

    $phloop_buffer .= $phloop_line;

    if (phloop_is_complete_statement($phloop_buffer)) {
      $phloop_stmt = phloop_prepare_debug_stmt($phloop_buffer);

      $phloop_pid = pcntl_fork();
      if ($phloop_pid == 0) {
        var_dump(eval($phloop_stmt));
      } elseif ($phloop_pid < 0) {
        printf("phloop error: failed to fork child\n");
      } else {
        pcntl_waitpid($phloop_pid, $phloop_status);
        if ($phloop_status != 65280) { // Fatal error
          exit(0);
        }
      }

      $phloop_buffer = '';
    }
  }
}

phloop_start();

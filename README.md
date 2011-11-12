# Boris: A tiny little REPL for PHP

Python has one. Ruby has one. Clojure has one. Now PHP has one too. Boris is
PHP's missing REPL (read-eval-print loop), allowing developers to experiment
with PHP code in the terminal in an interactive manner.  If you make a mistake,
it doesn't matter, Boris will report the error and stand to attention for
further input.

Everything you enter into Boris is evaluated and the result `var_dump()`'d
for inspection.  State is maintained between inputs, allowing you to gradually
build up a solution to a problem.

## Why?

I'm in the process of transitioning away from PHP and into Ruby.  I have come
to find PHP's lack of a real REPL to be frustrating and was not able to find an
existing implementation that was complete.  Boris weighs in at under 200 lines
of (procedural) code.

## Usage

I'll probably put this in PEAR, but right now you can get Boris from github:

    git clone git://github.com/d11wtq/boris.git
    cd boris
    ./boris

When Boris starts, you will be at the `boris>` prompt. PHP code you enter at
this prompt is evaluated.  If an expression spans multiple lines, Boris will
collect the input and then evaluate the expression when it is complete.  The
output is dumped with `var_dump()`.

    boris> $x = 1;
    int(1)
    boris> $y = 2;
    int(2)
    boris> "x + y = " . ($x + $y);
    string(9) "x + y = 3"
    boris>

You can also use Boris as part of a larger project (e.g. with your application
environment loaded).  Just include "boris.php" and call `boris_start();`.

## What about PHP's interactive mode?

PHP's interactive mode does not print the result of evaluating expressions, but
more importantly, it exits if you type something that produces a fatal error,
such as invoking a function/method that does not exist, or an uncaught
exception.

## Architecture Overview

Boris will only work on POSIX systems (Linux and Mac OS).  This is primarily
because it depends on the ability to fork. If anybody knows how to make this
approach work in Windows, do submit a pull request.

Boris is composed of two parts:

  1. A REPL worker process, which receives expressions to evaluate and print
  2. A readline client, which simply takes your input, sends it to the worker
     and then loops

If all errors in PHP were exceptions, building a REPL would be simple. This is
not the case, however.  Some PHP errors are truly fatal and cannot be caught.
In order to prevent such fatal errors from killing the REPL, the worker looks
something like this:

    for(;;) {
      $input = accept_some_input();
      if (fork_child()) {
        wait_for_child();
      } else { // inside child
        var_dump(eval($input));
        kill_parent();
      }
    }

The child is forked with all current variables and resources.  It evaluates the
input then kills the parent, then the loop continues inside the child, waiting
for the next input.

While the child is evaluating the input, the parent waits. The parent is
expecting the worst—that the child will die—at which point the parent continues
looping and does not terminate.  The state remains unchanged.

The readline part of Boris is much more straightforward.  It takes your input,
performs a (very) shallow parse on it, in order to decide if it needs to wait
for further input, or evaluate the input (one statement at a time) it has
received.  This is always the top-level parent process.

## Will it work with...?

Boris depends on the following PHP features:

  - The Readline functions
  - The PCNTL functions
  - The POSIX functions
  - The Socket functions

It has been written in PHP 5.3, but should work in PHP 5.2.  It will not work in
PHP 4.  There's no chance it can work on Windows, due to the dependency on POSIX
features.

## Tests?

Sorry, I just put this together in an afternoon, deliberately keeping it minimal.
It's literally a couple of hundred lines of code, with less than 10 functions,
containined in a single file.

# Boris: A tiny little, but robust REPL for PHP

Here's a 5 minute [video demonstration on YouTube](http://www.youtube.com/watch?v=xB5fJoX-dws).

Python has one. Ruby has one. Clojure has one. Now PHP has one too. Boris is
PHP's missing REPL (read-eval-print loop), allowing developers to experiment
with PHP code in the terminal in an interactive manner.  If you make a mistake,
it doesn't matter, Boris will report the error and stand to attention for
further input.

Everything you enter into Boris is evaluated and the result inspected so you
can understand what is happening.  State is maintained between inputs, allowing
you to gradually build up a solution to a problem.

## Why?

I'm in the process of transitioning away from PHP to Ruby.  I have come to find
PHP's lack of a real REPL to be frustrating and was not able to find an existing
implementation that was complete.  Boris weighs in at a few hundred lines of
fairly straightforward code.

## Usage

Boris is available via Packagist, or you can use it directly from this repo:

    git clone git://github.com/d11wtq/boris.git
    cd boris
    ./bin/boris

**Pro Tip**: Add boris to your $PATH for easy access.

When Boris starts, you will be at the `boris>` prompt. PHP code you enter at
this prompt is evaluated.  If an expression spans multiple lines, Boris will
collect the input and then evaluate the expression when it is complete. Press
CTRL-C to clear a multi-line input buffer if you make a mistake. The output
is dumped with `var_dump()` by default.

    boris> $x = 1;
    int(1)
    boris> $y = 2;
    int(2)
    boris> "x + y = " . ($x + $y);
    string(9) "x + y = 3"
    boris> exit;

You can also use CTRL-D to exit the REPL.

### Cancelling long-running operations

Long-running operations, such as infinite loops, may be cancelled at any time
without quitting the REPL, by using CTRL-C while the operation is running.

    boris> for ($i = 0; ; ++$i) {
        *>   if ($i % 2 == 0) printf("Tick\n");
        *>   else             printf("Tock\n");
        *>   sleep(1);
        *> }
    Tick
    Tock
    Tick
    Tock
    Tick
    Tock
    Tick
    ^CCancelling...
    boris>

### Using Boris with your application loaded

You can also use Boris as part of a larger project (e.g. with your application
environment loaded).

    require_once 'vendor/autoload.php';

    $boris = new \Boris\Boris('myapp> ');
    $boris->start();

The constructor parameter is optional and changes the prompt.

If you want to pass local variables straight into Boris (e.g. parts of your
application), you can do that too (thanks to @dhotston):

    $boris = new \Boris\Boris('myapp> ');
    $boris->setLocal(array('appContext' => $appContext));
    $boris->start();

In the above example, $appContext will be present inside the REPL.

### Customizing the output

After each expression you enter, Boris passes it through an Inspector to get a
representation that is useful for debugging. The default is just to var_dump() the
value, but you can change this behaviour.

Any object that has an `inspect($variable)` method may be used for this purpose.

    $boris->setInspector(new BlinkInspector());

Boris comes with two alternatives out of the box:

  * \Boris\DumpInspector, which uses var_dump() and is the default
  * \Boris\ExportInspector, which uses var_export()

Note that you can change this from inside the REPL too:

    boris> $this->setInspector(new \Boris\ExportInspector());
    -> NULL
    boris> "Test";
    -> 'Test'

## What about PHP's interactive mode?

PHP's interactive mode does not print the result of evaluating expressions and
more importantly, it exits if you type something that produces a fatal error,
such as invoking a function/method that does not exist, or an uncaught
exception.  Boris is designed to be robust, like other REPLs, so you can
experiment with things that you know may error, without losing everything.

## Architecture Overview

This section of the README only applies to those curious enough to read the
code. Boris is quite different to other PHP REPLs, because it deals with fatal
errors (not Exceptions, fatal errors) in a special way.

Boris will only work on POSIX systems (Linux and Mac OS).  This is primarily
because it depends on the ability to fork, but also because it plays with signals
a lot too.

Boris is made up of two parts:

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
expecting the worst—that the child will die abnormally—at which point the parent
continues waiting for input and does not terminate.  The state remains unchanged.

After each expression is evaluated, the worker reports back to the main process
with a status code of 0 (keep running) or 1 (terminate).

The main process (readline) of Boris is much more straightforward.  It takes
your input, performs a (very) shallow parse on it, in order to decide if it
needs to wait for further input, or evaluate the input (one statement at a time)
it has received.  If the worker reports back with a status code of 1, the process
terminates, otherwise the next iteration of the loop is entered.

## Will it work with...?

Boris depends on the following PHP features:

  - PHP >= 5.3
  - The Readline functions
  - The PCNTL functions
  - The POSIX functions
  - The Socket functions

There's no chance it can work on Windows, due to the dependency on POSIX
features (the code is almost entirely dependant on POSIX).

## Copyright & Licensing

Boris is written and maintained by Chris Corbyn (@d11wtq). You can use the
code as you see fit. See the LICENSE file for details.

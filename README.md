This is a fork of [borisrepl/boris](https://github.com/borisrepl/boris).
This fork introduces the `--autoload` option (shothand `-a`) to Boris.
This option makes Boris automatically detect if you are in a composer project and
automatically load the classes of the project using the `autoload.php` script.

<hr/>

# Boris

A tiny, but robust REPL for PHP.

[![Code Climate](https://codeclimate.com/github/borisrepl/boris/badges/gpa.svg)](https://codeclimate.com/github/borisrepl/boris)
[![Build Status](https://travis-ci.org/borisrepl/boris.svg?branch=master)](https://travis-ci.org/borisrepl/boris)


> **Announcement:** I'm looking to add one or two additional collaborators with
> commit access. If you are actively involved in open source and have a GitHub
> profile for review, ping me on Twitter (@d11wtq) to express your interest.
> Experienced developers with active GitHub projects only.

![Demo](http://dl.dropbox.com/u/508607/BorisDemo-v4.gif "Quick Demo")

Python has one. Ruby has one. Clojure has one. Now PHP has one, too. Boris is
PHP's missing REPL (read-eval-print loop), allowing developers to experiment
with PHP code in the terminal in an interactive manner.  If you make a mistake,
it doesn't matter, Boris will report the error and stand to attention for
further input.

Everything you enter into Boris is evaluated and the result inspected so you
can understand what is happening.  State is maintained between inputs, allowing
you to gradually build up a solution to a problem.

> __Note:__ The PCNTL function which is required to run Boris is not available on Windows platforms.

## Why?

I'm in the process of transitioning away from PHP to Ruby.  I have come to find
PHP's lack of a real REPL to be frustrating and was not able to find an existing
implementation that was complete.  Boris weighs in at a few hundred lines of
fairly straightforward code.


## Usage

Check out our wonderful [wiki] for usage instructions.


## Contributing

We're committed to a loosely-coupled architecture for Boris and would love to get your contributions.

Before jumping in, check out our **[Contributing] [contributing]** page on the wiki!

## Contributing

We're using [PHPUnit](https://phpunit.de/) for testing. To run all the tests,

    phpunit --bootstrap tests/autoload.php -c tests.xml

## Core Team

This module was originally developed by [Chris Corbyn](https://github.com/d11wtq), and is now maintained by [Tejas Manohar](https://github.com/tejasmanohar), [Dennis Hotson](https://github.com/dhotson), and [other wonderful contributors](https://github.com/borisrepl/boris/graphs/contributors).

## Copyright & Licensing

See the [LICENSE] file for details.

[LICENSE]: https://github.com/borisrepl/boris/blob/master/LICENSE
[wiki]: https://github.com/borisrepl/boris/wiki
[contributing]: https://github.com/borisrepl/boris/blob/master/CONTRIBUTING.md
[Chris Corbyn]: https://github.com/borisrepl

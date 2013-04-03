<?php
include __DIR__ . "/../vendor/autoload.php";

use Symfony\Component\Finder\Finder;

define('BORIS_VERSION', "0.1.0");

compile();

function compile($pharFile = 'boris.phar') {
    if (file_exists($pharFile)) {
        unlink($pharFile);
    }

    $phar = new \Phar($pharFile, 0, 'boris.phar');
    $phar->setSignatureAlgorithm(\Phar::SHA1);

    $phar->startBuffering();

    $finder = new Finder();
    $finder->files()
        ->ignoreVCS(true)
        ->name('*.php')
        ->notName('autoload.php')
        ->in(__DIR__.'/../lib')
    ;

    foreach ($finder as $file) {
        addFile($phar, $file);
    }
    addFile($phar, new \SplFileInfo(__DIR__.'/../lib/autoload.php'));

    $content = file_get_contents(__DIR__.'/boris');
    $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
    $phar->addFromString('bin/boris', $content);

    // Stubs
    $phar->setStub(getStub());
    $phar->stopBuffering();

    // disabled for interoperability with systems without gzip ext
    //$phar->compressFiles(\Phar::GZ);

    addFile($phar, new \SplFileInfo(__DIR__.'/../LICENSE'), false);

    unset($phar);
}

function addFile($phar, $file, $strip = true) {
    $path = str_replace(dirname(__DIR__).DIRECTORY_SEPARATOR, '', $file->getRealPath());

    $content = file_get_contents($file);
    if ($strip) {
        $content = stripWhitespace($content);
    } else {
        $content = "\n".$content."\n";
    }
    $content = str_replace('@package_version@', BORIS_VERSION, $content);

    $phar->addFromString($path, $content);
}

function getStub() {
    $stub = <<<'EOF'
#!/usr/bin/env php
<?php
Phar::mapPhar('boris.phar');
require 'phar://boris.phar/bin/boris';
__HALT_COMPILER();
EOF;

    return $stub;
}

function stripWhitespace($source) {
    if (!function_exists('token_get_all')) {
        return $source;
    }

    $output = '';
    foreach (token_get_all($source) as $token) {
        if (is_string($token)) {
            $output .= $token;
        } elseif (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
            $output .= str_repeat("\n", substr_count($token[1], "\n"));
        } elseif (T_WHITESPACE === $token[0]) {
            // reduce wide spaces
            $whitespace = preg_replace('{[ \t]+}', ' ', $token[1]);
            // normalize newlines to \n
            $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
            // trim leading spaces
            $whitespace = preg_replace('{\n +}', "\n", $whitespace);
            $output .= $whitespace;
        } else {
            $output .= $token[1];
        }
    }

    return $output;
}
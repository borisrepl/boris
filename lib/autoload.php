<?php

/**
 * Custom autoloader for non-composer installations.
 */
spl_autoload_register(function($class) {
	if ($class[0] == '\\') {
		$class = substr($class, 1);
	}

	$path = sprintf('%s/%s.php', dirname(__FILE__), implode('/', explode('\\', $class)));

	if (is_file($path)) {
		require_once($path);
	}
});

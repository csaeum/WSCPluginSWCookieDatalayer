<?php
/**
 * PHPUnit Bootstrap File for WSC Cookie DataLayer Plugin
 */

declare(strict_types=1);

// Find autoloader
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php', // Plugin standalone
    __DIR__ . '/../../../../vendor/autoload.php', // Plugin in Shopware
];

$autoloaderFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    echo "Autoloader not found. Please run: composer install\n";
    exit(1);
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

<?php

/**
 * @file
 * PHPUnit bootstrap for standalone and Drupal-project test runs.
 */

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

$module_root = dirname(__DIR__);
$autoload = $module_root . '/vendor/autoload.php';
if (!is_file($autoload)) {
  $autoload = (string) getenv('DRUPAL_AUTOLOAD');
}
if ($autoload === '' || !is_file($autoload)) {
  throw new RuntimeException(
    'Run composer install or set DRUPAL_AUTOLOAD to a Drupal Composer autoload.php file.',
  );
}

$loader = require $autoload;
if ($loader instanceof ClassLoader) {
  $loader->addPsr4('Drupal\\grok_doc\\', $module_root . '/src');
  $loader->addPsr4('Drupal\\Tests\\grok_doc\\', __DIR__ . '/src');
}

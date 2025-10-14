<?php

/**
 * Script to uninstall and reinstall the s3_uppy module.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require_once 'vendor/autoload.php';

$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();

$container = $kernel->getContainer();
$module_installer = $container->get('module_installer');

echo "Uninstalling s3_uppy module...\n";
try {
  $module_installer->uninstall(['s3_uppy']);
  echo "Module uninstalled successfully.\n";
} catch (Exception $e) {
  echo "Error uninstalling: " . $e->getMessage() . "\n";
}

echo "Installing s3_uppy module...\n";
try {
  $module_installer->install(['s3_uppy']);
  echo "Module installed successfully.\n";
} catch (Exception $e) {
  echo "Error installing: " . $e->getMessage() . "\n";
}

echo "Rebuilding cache...\n";
drupal_flush_all_caches();
echo "Cache rebuilt successfully.\n";

echo "\nDone! Module reinstalled and caches cleared.\n";

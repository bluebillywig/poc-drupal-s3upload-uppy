<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require_once '/var/www/html/autoload.php';
$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();
$container = $kernel->getContainer();
$container->get('module_installer')->install(['s3_uppy']);

echo "Module s3_uppy has been enabled successfully.\n";

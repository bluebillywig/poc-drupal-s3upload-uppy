<?php

/**
 * Import media type configuration.
 */

use Drupal\Core\Config\FileStorage;
use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

$autoloader = require_once 'vendor/autoload.php';
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();

$config_path = 'web/modules/custom/s3_uppy/config/install';

// List of config files to import
$configs = [
  'field.storage.media.field_mediaclip_id',
  'media.type.bluebillywig_video',
  'field.field.media.bluebillywig_video.field_mediaclip_id',
  'core.entity_view_display.media.bluebillywig_video.default',
];

$config_factory = \Drupal::configFactory();

foreach ($configs as $config_name) {
  $file = $config_path . '/' . $config_name . '.yml';

  if (file_exists($file)) {
    echo "Importing $config_name...\n";

    $data = Yaml::parseFile($file);
    $config = $config_factory->getEditable($config_name);
    $config->setData($data)->save();

    echo "  ✓ Imported $config_name\n";
  } else {
    echo "  ✗ File not found: $file\n";
  }
}

// Clear cache
echo "\nClearing cache...\n";
\Drupal::service('cache.render')->invalidateAll();
\Drupal::service('cache.page')->invalidateAll();
\Drupal::service('cache.dynamic_page_cache')->invalidateAll();
echo "✓ Done!\n";

<?php

namespace Drupal\s3_uppy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media\MediaInterface;

/**
 * Controller for viewing media entities.
 */
class MediaViewController extends ControllerBase {

  /**
   * Display a media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return array
   *   Render array.
   */
  public function view(MediaInterface $media) {
    $view_builder = $this->entityTypeManager()->getViewBuilder('media');
    $build = $view_builder->view($media, 'default');

    $build['#title'] = $media->getName();

    return $build;
  }

}

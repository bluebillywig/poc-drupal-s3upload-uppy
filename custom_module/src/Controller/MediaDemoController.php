<?php

namespace Drupal\s3_uppy\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Demo page showing all Blue Billywig videos.
 */
class MediaDemoController extends ControllerBase {

  /**
   * Display all Blue Billywig videos.
   */
  public function listVideos() {
    $build = [];

    $build['intro'] = [
      '#markup' => '<h2>Blue Billywig Videos</h2><p>All uploaded videos are listed below with their embedded players.</p>',
    ];

    // Load all bluebillywig_video media entities
    $media_storage = $this->entityTypeManager()->getStorage('media');
    $query = $media_storage->getQuery()
      ->condition('bundle', 'bluebillywig_video')
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);

    $media_ids = $query->execute();

    if (empty($media_ids)) {
      $build['empty'] = [
        '#markup' => '<p>No videos uploaded yet. <a href="/s3-video-upload">Upload a video</a>.</p>',
      ];
      return $build;
    }

    $media_entities = $media_storage->loadMultiple($media_ids);
    $view_builder = $this->entityTypeManager()->getViewBuilder('media');

    foreach ($media_entities as $media) {
      $build['video_' . $media->id()] = [
        '#type' => 'container',
        '#attributes' => [
          'style' => 'margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px;',
        ],
      ];

      $build['video_' . $media->id()]['title'] = [
        '#markup' => '<h3>' . $media->getName() . '</h3>',
      ];

      $build['video_' . $media->id()]['info'] = [
        '#markup' => '<p><strong>Media ID:</strong> ' . $media->id() . ' | <strong>Created:</strong> ' . date('Y-m-d H:i:s', $media->getCreatedTime()) . '</p>',
      ];

      $build['video_' . $media->id()]['player'] = $view_builder->view($media, 'default');
    }

    return $build;
  }

}

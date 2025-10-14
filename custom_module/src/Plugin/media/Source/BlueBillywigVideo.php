<?php

namespace Drupal\s3_uppy\Plugin\media\Source;

use Drupal\media\MediaSourceBase;
use Drupal\media\MediaInterface;

/**
 * Blue Billywig Video media source.
 *
 * @MediaSource(
 *   id = "bluebillywig_video",
 *   label = @Translation("Blue Billywig Video"),
 *   description = @Translation("Use Blue Billywig OVP videos."),
 *   allowed_field_types = {"string"},
 *   default_thumbnail_filename = "video.png"
 * )
 */
class BlueBillywigVideo extends MediaSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [
      'mediaclip_id' => $this->t('MediaClip ID'),
      'title' => $this->t('Title'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    $mediaclip_id = $this->getSourceFieldValue($media);

    switch ($attribute_name) {
      case 'mediaclip_id':
        return $mediaclip_id;

      case 'default_name':
        return 'Blue Billywig Video ' . $mediaclip_id;

      default:
        return parent::getMetadata($media, $attribute_name);
    }
  }

}

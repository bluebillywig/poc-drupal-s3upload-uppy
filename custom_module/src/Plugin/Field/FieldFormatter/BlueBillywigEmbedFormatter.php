<?php

namespace Drupal\s3_uppy\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Markup;

/**
 * Plugin implementation of the 'bluebillywig_embed' formatter.
 *
 * @FieldFormatter(
 *   id = "bluebillywig_embed",
 *   label = @Translation("Blue Billywig Embed"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class BlueBillywigEmbedFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $mediaclip_id = $item->value;

      if (empty($mediaclip_id)) {
        continue;
      }

      // Get configuration
      $config = \Drupal::config('s3_uppy.settings');
      $playout = $config->get('bb_playout') ?: getenv('BB_PLAYOUT') ?: 'default';

      // Fetch embed code from OVP
      try {
        $ovp_client = new \Drupal\s3_uppy\Service\BlueBillywigOvpClient();
        $embed_code = $ovp_client->getEmbedCode($mediaclip_id, $playout);

        // Render the embed code as markup
        // The embed code contains JavaScript that must be rendered as-is
        $elements[$delta] = [
          '#markup' => Markup::create('<div class="bluebillywig-video">' . $embed_code . '</div>'),
        ];
      }
      catch (\Exception $e) {
        \Drupal::logger('s3_uppy')->error('Failed to fetch embed code for MediaClip @id: @error', [
          '@id' => $mediaclip_id,
          '@error' => $e->getMessage(),
        ]);

        $elements[$delta] = [
          '#markup' => '<p>Error loading video.</p>',
        ];
      }
    }

    return $elements;
  }

}

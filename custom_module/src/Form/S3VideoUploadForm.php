<?php

namespace Drupal\s3_uppy\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for uploading videos to S3.
 */
class S3VideoUploadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 's3_video_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Attach Uppy library
    $form['#attached']['library'][] = 's3_uppy/uppy';

    // Pass settings to JavaScript
    $form['#attached']['drupalSettings']['s3Uppy'] = [
      'generateUrlEndpoint' => '/s3-uppy/generate-url',
      'uploadCompleteEndpoint' => '/s3-uppy/upload-complete',
      'generateUploadIdentifierEndpoint' => '/s3-uppy/generate-upload-identifier',
      'maxFileSize' => 1024 * 1024 * 1024 * 20, // 20GB
      'allowedFileTypes' => ['.mp4', '.mov', '.avi', '.webm', '.ogg', '.mxf', '.mpg', '.mpeg', '.mkv'],
    ];

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>Upload video files directly to S3. Supported formats: MP4, MOV, AVI, WebM, OGG, MXF, MPG, MPEG, MKV. Maximum size: 20GB.</p>',
    ];

    $form['ovp_info'] = [
      '#type' => 'markup',
      '#markup' => '<div id="ovp-info" style="margin-bottom: 1em;"><em>Select or drop a video file to begin...</em></div>',
    ];

    // Hidden field to store upload identifier
    $form['upload_identifier'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'upload-identifier-field',
      ],
    ];

    // Container for Uppy
    $form['uppy_container'] = [
      '#type' => 'markup',
      '#markup' => '<div id="uppy-dashboard"></div><div id="upload-status"></div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Form submission is handled by JavaScript/Uppy
    // This method is required but not used in this case
  }

}

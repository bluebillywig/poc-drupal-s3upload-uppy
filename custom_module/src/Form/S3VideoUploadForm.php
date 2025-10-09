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
      'maxFileSize' => 1024 * 1024 * 1024 * 2, // 2GB
      'allowedFileTypes' => ['.mp4', '.mov', '.avi', '.webm', '.ogg'],
    ];

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>Upload video files directly to S3. Supported formats: MP4, MOV, AVI, WebM, OGG. Maximum size: 2GB.</p>',
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

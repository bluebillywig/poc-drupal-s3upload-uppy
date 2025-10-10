<?php

namespace Drupal\s3_uppy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure S3 Uppy settings.
 */
class S3UppySettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['s3_uppy.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 's3_uppy_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('s3_uppy.settings');

    $form['aws'] = [
      '#type' => 'details',
      '#title' => $this->t('AWS S3 Configuration'),
      '#open' => TRUE,
    ];

    $form['aws']['aws_access_key_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AWS Access Key ID'),
      '#default_value' => $config->get('aws_access_key_id') ?: getenv('AWS_ACCESS_KEY_ID'),
      '#required' => TRUE,
      '#description' => $this->t('Your AWS access key ID. Falls back to AWS_ACCESS_KEY_ID environment variable.'),
    ];

    $form['aws']['aws_secret_access_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AWS Secret Access Key'),
      '#default_value' => $config->get('aws_secret_access_key') ?: getenv('AWS_SECRET_ACCESS_KEY'),
      '#required' => TRUE,
      '#description' => $this->t('Your AWS secret access key. Falls back to AWS_SECRET_ACCESS_KEY environment variable.'),
      '#attributes' => ['autocomplete' => 'off'],
    ];

    $form['aws']['aws_s3_bucket'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AWS S3 Bucket'),
      '#default_value' => $config->get('aws_s3_bucket') ?: getenv('AWS_S3_BUCKET'),
      '#required' => TRUE,
      '#description' => $this->t('The S3 bucket name for uploads. Falls back to AWS_S3_BUCKET environment variable.'),
    ];

    $form['aws']['aws_s3_region'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AWS S3 Region'),
      '#default_value' => $config->get('aws_s3_region') ?: getenv('AWS_S3_REGION') ?: 'us-east-1',
      '#required' => TRUE,
      '#description' => $this->t('AWS region (e.g., eu-west-1, us-east-1). Falls back to AWS_S3_REGION environment variable or us-east-1.'),
    ];

    $form['aws']['aws_s3_upload_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AWS S3 Upload Prefix'),
      '#default_value' => $config->get('aws_s3_upload_prefix') ?: getenv('AWS_S3_UPLOAD_PREFIX') ?: 'upload/',
      '#description' => $this->t('Path prefix for uploaded files (e.g., upload/YOUR_PUBLICATION/). Falls back to AWS_S3_UPLOAD_PREFIX environment variable or upload/.'),
    ];

    $form['aws']['aws_s3_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AWS S3 Endpoint (Optional)'),
      '#default_value' => $config->get('aws_s3_endpoint') ?: getenv('AWS_S3_ENDPOINT'),
      '#description' => $this->t('Custom S3 endpoint for MinIO or other S3-compatible services. Leave empty for standard AWS S3.'),
    ];

    $form['bluebillywig'] = [
      '#type' => 'details',
      '#title' => $this->t('Blue Billywig OVP Configuration'),
      '#open' => TRUE,
    ];

    $form['bluebillywig']['bb_publication'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publication Name'),
      '#default_value' => $config->get('bb_publication') ?: getenv('BB_PUBLICATION'),
      '#description' => $this->t('Your Blue Billywig publication name. API hostname will be constructed as {publication}.bbvms.com. Falls back to BB_PUBLICATION environment variable.'),
    ];

    $form['bluebillywig']['bb_api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Secret'),
      '#default_value' => $config->get('bb_api_secret') ?: getenv('BB_API_SECRET'),
      '#description' => $this->t('Format: &lt;numerical_id&gt;-&lt;secret&gt; (e.g., 123-mysecretstring). Falls back to BB_API_SECRET environment variable.'),
      '#attributes' => ['autocomplete' => 'off'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('s3_uppy.settings')
      ->set('aws_access_key_id', $form_state->getValue('aws_access_key_id'))
      ->set('aws_secret_access_key', $form_state->getValue('aws_secret_access_key'))
      ->set('aws_s3_bucket', $form_state->getValue('aws_s3_bucket'))
      ->set('aws_s3_region', $form_state->getValue('aws_s3_region'))
      ->set('aws_s3_upload_prefix', $form_state->getValue('aws_s3_upload_prefix'))
      ->set('aws_s3_endpoint', $form_state->getValue('aws_s3_endpoint'))
      ->set('bb_publication', $form_state->getValue('bb_publication'))
      ->set('bb_api_secret', $form_state->getValue('bb_api_secret'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

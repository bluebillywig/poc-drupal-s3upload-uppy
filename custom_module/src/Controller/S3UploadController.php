<?php

namespace Drupal\s3_uppy\Controller;

// Load AWS SDK autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

use Aws\S3\S3Client;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for S3 upload operations.
 */
class S3UploadController extends ControllerBase {

  /**
   * Get configuration value with fallback to environment variable.
   */
  protected function getConfigValue($key, $env_var, $default = '') {
    $config = \Drupal::config('s3_uppy.settings');
    $value = $config->get($key);

    // Fall back to environment variable if config is empty
    if (empty($value)) {
      $value = getenv($env_var) ?: $default;
    }

    return $value;
  }

  /**
   * Generate a presigned URL for S3 upload.
   */
  public function generatePresignedUrl(Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    $filename = $data['filename'] ?? '';
    $filetype = $data['filetype'] ?? '';
    $uploadidentifier = $data['uploadidentifier'] ?? '';

    // Debug logging
    \Drupal::logger('s3_uppy')->debug('Received data: @data', [
      '@data' => print_r($data, TRUE),
    ]);

    // Validate upload identifier
    if (empty($uploadidentifier)) {
      return new JsonResponse([
        'error' => 'Upload identifier is required.',
        'received_data' => $data,
      ], 400);
    }

    // Validate file type (only allow video files)
    $allowed_types = [
      'video/mp4',
      'video/quicktime',
      'video/x-msvideo',
      'video/webm',
      'video/ogg',
      'application/mxf',
      'video/x-mxf',
      'video/mpeg',
      'video/x-matroska',
      'video/mkv',
    ];
    if (!in_array($filetype, $allowed_types)) {
      return new JsonResponse([
        'error' => 'Invalid file type. Only video files are allowed.',
      ], 400);
    }

    // Sanitize filename
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    $prefix = $this->getConfigValue('aws_s3_upload_prefix', 'AWS_S3_UPLOAD_PREFIX', 'upload/');
    $key = $prefix . uniqid() . '_' . $filename;

    try {
      // Get AWS credentials from config or environment
      $accessKey = $this->getConfigValue('aws_access_key_id', 'AWS_ACCESS_KEY_ID');
      $secretKey = $this->getConfigValue('aws_secret_access_key', 'AWS_SECRET_ACCESS_KEY');
      $region = $this->getConfigValue('aws_s3_region', 'AWS_S3_REGION', 'us-east-1');
      $bucket = $this->getConfigValue('aws_s3_bucket', 'AWS_S3_BUCKET');
      $endpoint = $this->getConfigValue('aws_s3_endpoint', 'AWS_S3_ENDPOINT');

      // Initialize S3 client
      $s3Config = [
        'version' => 'latest',
        'region' => $region,
        'credentials' => [
          'key' => $accessKey,
          'secret' => $secretKey,
        ],
      ];

      // Add endpoint if configured (for MinIO or other S3-compatible services)
      if (!empty($endpoint)) {
        $s3Config['endpoint'] = $endpoint;
      }

      $s3Client = new S3Client($s3Config);

      // Create presigned request
      $cmd = $s3Client->getCommand('PutObject', [
        'Bucket' => $bucket,
        'Key' => $key,
        'ContentType' => $filetype,
        'ACL' => 'private',
        'Metadata' => [
          'uploadidentifier' => $uploadidentifier,
        ],
      ]);

      // Generate presigned URL (valid for 15 minutes)
      $request = $s3Client->createPresignedRequest($cmd, '+15 minutes');
      $presignedUrl = (string) $request->getUri();

      return new JsonResponse([
        'url' => $presignedUrl,
        'key' => $key,
        'bucket' => $bucket,
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('s3_uppy')->error('Error generating presigned URL: @error', [
        '@error' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => 'Failed to generate upload URL',
      ], 500);
    }
  }

  /**
   * Generate upload identifier from Blue Billywig OVP.
   */
  public function generateUploadIdentifier(Request $request) {
    try {
      $data = json_decode($request->getContent(), TRUE);
      $filename = $data['filename'] ?? '';
      $title = $data['title'] ?? '';
      $description = $data['description'] ?? '';

      // Generate a GUID for sourceid
      $guid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
      );

      // Initialize OVP client and register upload
      $ovpClient = new \Drupal\s3_uppy\Service\BlueBillywigOvpClient();
      $result = $ovpClient->registerUpload($guid, $filename, $title, $description);

      return new JsonResponse([
        'uploadidentifier' => $result['uploadidentifier'],
        'mediaclipId' => $result['mediaclipId'],
        'guid' => $guid,
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('s3_uppy')->error('Error generating upload identifier: @error', [
        '@error' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => 'Failed to generate upload identifier: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Get embed code for a MediaClip.
   */
  public function getEmbedCode(Request $request) {
    try {
      $data = json_decode($request->getContent(), TRUE);
      $mediaclipId = $data['mediaclipId'] ?? '';

      if (empty($mediaclipId)) {
        return new JsonResponse([
          'error' => 'MediaClip ID is required',
        ], 400);
      }

      // Get playout config from settings
      $playout = $this->getConfigValue('bb_playout', 'BB_PLAYOUT', 'default');

      // Get embed code from OVP
      $ovpClient = new \Drupal\s3_uppy\Service\BlueBillywigOvpClient();
      $embedCode = $ovpClient->getEmbedCode($mediaclipId, $playout);

      return new JsonResponse([
        'embedCode' => $embedCode,
        'mediaclipId' => $mediaclipId,
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('s3_uppy')->error('Error getting embed code: @error', [
        '@error' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'error' => 'Failed to get embed code: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Handle upload completion callback.
   */
  public function uploadComplete(Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    $key = $data['key'] ?? '';
    $filename = $data['filename'] ?? '';

    // Log the successful upload
    \Drupal::logger('s3_uppy')->info('Video uploaded successfully: @filename (S3 key: @key)', [
      '@filename' => $filename,
      '@key' => $key,
    ]);

    // Here you could:
    // - Create a node/entity to track the uploaded file
    // - Send notifications
    // - Trigger additional processing
    // - Store metadata in the database

    return new JsonResponse([
      'success' => TRUE,
      'message' => 'Upload completed successfully',
      'key' => $key,
    ]);
  }

}

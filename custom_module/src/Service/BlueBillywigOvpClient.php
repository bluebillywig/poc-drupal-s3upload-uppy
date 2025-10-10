<?php

namespace Drupal\s3_uppy\Service;

use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;

/**
 * Blue Billywig OVP API client with TOTP authentication.
 */
class BlueBillywigOvpClient {

  /**
   * The OVP hostname.
   *
   * @var string
   */
  protected $hostname;

  /**
   * The OVP API numerical ID.
   *
   * @var string
   */
  protected $apiId;

  /**
   * The OVP API secret.
   *
   * @var string
   */
  protected $apiSecret;

  /**
   * Constructor.
   */
  public function __construct() {
    $apiSecretFull = getenv('OVP_API_SECRET');
    $publication = getenv('BB_PUBLICATION');

    // Build hostname from publication name
    if (empty($publication)) {
      throw new \Exception('BB_PUBLICATION environment variable is required');
    }
    $this->hostname = $publication . '.bbvms.com';

    // Parse the API secret (format: 123-secret)
    // The ID and secret are separated, but only the secret is used for TOTP
    if ($apiSecretFull && strpos($apiSecretFull, '-') !== FALSE) {
      list($this->apiId, $this->apiSecret) = explode('-', $apiSecretFull, 2);
    }
    else {
      throw new \Exception('Invalid OVP_API_SECRET format. Expected: <id>-<secret>');
    }
  }

  /**
   * Generate TOTP token for authentication.
   *
   * @return string
   *   The TOTP token in format: <id>-<otp>
   */
  protected function generateRpcToken() {
    // Base32 encode ONLY the secret (without the ID prefix) before using with TOTP
    $base32Secret = Base32::encodeUpper($this->apiSecret);

    $totp = TOTP::createFromSecret($base32Secret);
    $totp->setPeriod(120); // 120 seconds step/window
    $totp->setDigits(10); // 10-digit OTP
    $otp = $totp->now();

    return $this->apiId . '-' . $otp;
  }

  /**
   * Create a MediaClip in the OVP.
   *
   * @param string $guid
   *   The source ID (GUID).
   * @param string $originalFilename
   *   The original filename.
   * @param string $title
   *   The clip title.
   * @param string $description
   *   The clip description.
   *
   * @return array
   *   The MediaClip object with id field.
   *
   * @throws \Exception
   */
  public function createMediaClip($guid, $originalFilename = '', $title = '', $description = '') {
    $url = 'https://' . $this->hostname . '/sapi/mediaclip';

    $payload = ['sourceid' => $guid];
    if (!empty($originalFilename)) {
      $payload['originalfilename'] = $originalFilename;
    }
    if (!empty($title)) {
      $payload['title'] = $title;
    }
    if (!empty($description)) {
      $payload['description'] = $description;
    }

    $payloadJson = json_encode($payload);
    $rpcToken = $this->generateRpcToken();

    // Debug logging to stderr
    error_log("OVP CREATE MEDIACLIP - Payload: " . $payloadJson);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'rpctoken: ' . $rpcToken,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
      throw new \Exception('CURL error: ' . $curlError);
    }

    if ($httpCode !== 200) {
      throw new \Exception('OVP API error (HTTP ' . $httpCode . '): ' . $response);
    }

    $data = json_decode($response, TRUE);
    if (!isset($data['id'])) {
      throw new \Exception('MediaClip response missing id field');
    }

    return $data;
  }

  /**
   * Get upload identifier from OVP.
   *
   * @param int $mediaclipId
   *   The MediaClip ID.
   *
   * @return string
   *   The upload identifier.
   *
   * @throws \Exception
   */
  public function getUploadIdentifier($mediaclipId) {
    $url = 'https://' . $this->hostname . '/sapi/awsupload?autoPublish=false&type=mediaclip&mediaclipId=' . $mediaclipId;
    $rpcToken = $this->generateRpcToken();

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'rpctoken: ' . $rpcToken,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
      throw new \Exception('CURL error: ' . $curlError);
    }

    if ($httpCode !== 200) {
      throw new \Exception('OVP API error (HTTP ' . $httpCode . '): ' . $response);
    }

    $data = json_decode($response, TRUE);
    if (!isset($data['uploadIdentifier'])) {
      throw new \Exception('awsupload response missing uploadIdentifier field');
    }

    return $data['uploadIdentifier'];
  }

  /**
   * Register upload identifier in OVP backend.
   *
   * This creates a MediaClip and retrieves the upload identifier.
   *
   * @param string $guid
   *   The source ID (GUID).
   * @param string $originalFilename
   *   The original filename.
   * @param string $title
   *   The clip title.
   * @param string $description
   *   The clip description.
   *
   * @return array
   *   Array with 'uploadidentifier' and 'mediaclipId'.
   *
   * @throws \Exception
   */
  public function registerUpload($guid, $originalFilename = '', $title = '', $description = '') {
    // Step 1: Create MediaClip
    $mediaclip = $this->createMediaClip($guid, $originalFilename, $title, $description);
    $mediaclipId = $mediaclip['id'];

    // Step 2: Get upload identifier
    $uploadIdentifier = $this->getUploadIdentifier($mediaclipId);

    return [
      'uploadidentifier' => $uploadIdentifier,
      'mediaclipId' => $mediaclipId,
    ];
  }

}

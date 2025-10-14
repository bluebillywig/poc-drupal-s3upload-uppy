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
    // Get config with fallback to environment variables
    $config = \Drupal::config('s3_uppy.settings');

    $apiSecretFull = $config->get('bb_api_secret');
    if (empty($apiSecretFull)) {
      $apiSecretFull = getenv('BB_API_SECRET');
    }

    $publication = $config->get('bb_publication');
    if (empty($publication)) {
      $publication = getenv('BB_PUBLICATION');
    }

    // Build hostname from publication name
    if (empty($publication)) {
      throw new \Exception('BB_PUBLICATION configuration or environment variable is required');
    }
    $this->hostname = $publication . '.bbvms.com';

    // Parse the API secret (format: 123-secret)
    // The ID and secret are separated, but only the secret is used for TOTP
    if ($apiSecretFull && strpos($apiSecretFull, '-') !== FALSE) {
      list($this->apiId, $this->apiSecret) = explode('-', $apiSecretFull, 2);
    }
    else {
      throw new \Exception('Invalid BB_API_SECRET format. Expected: <id>-<secret>');
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
   * Get embed code for a MediaClip.
   *
   * @param int $mediaclipId
   *   The MediaClip ID.
   * @param string $playout
   *   The playout configuration name (default: 'default').
   *
   * @return string
   *   The JavaScript embed code.
   *
   * @throws \Exception
   */
  public function getEmbedCode($mediaclipId, $playout = 'default') {
    $url = 'https://' . $this->hostname . '/sapi/embedcode/' . $mediaclipId . '/' . $playout . '/javascript';
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

    // Parse JSON response and extract embed code
    $data = json_decode($response, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception('Invalid JSON response from OVP API: ' . json_last_error_msg());
    }

    if (!isset($data['body'])) {
      throw new \Exception('Missing "body" field in OVP API response');
    }

    return $data['body'];
  }

  /**
   * Search for MediaClips in the OVP.
   *
   * @param string $query
   *   The search query string.
   * @param array $filterQueries
   *   Optional array of filter queries (e.g., ['status:published']).
   * @param string $sort
   *   Optional sort parameter (e.g., 'createddate desc').
   * @param int $limit
   *   Optional limit for number of results (default: 20).
   * @param int $offset
   *   Optional offset for pagination (default: 0).
   *
   * @return array
   *   Search results with 'items' array and 'totalResults' count.
   *
   * @throws \Exception
   */
  public function searchMediaClips($query = '', array $filterQueries = [], $sort = 'createddate desc', $limit = 20, $offset = 0) {
    $url = 'https://' . $this->hostname . '/sapi/mediaclip';

    // Build query parameters
    $params = [];
    if (!empty($query)) {
      $params['q'] = $query;
    }

    // Add filter queries
    foreach ($filterQueries as $fq) {
      $params['fq'][] = $fq;
    }

    if (!empty($sort)) {
      $params['sort'] = $sort;
    }

    $params['limit'] = $limit;
    $params['offset'] = $offset;

    // Build URL with query string
    $queryString = http_build_query($params);
    $fullUrl = $url . '?' . $queryString;

    $rpcToken = $this->generateRpcToken();

    // Debug logging
    error_log("OVP SEARCH - URL: " . $fullUrl);
    error_log("OVP SEARCH - RPC Token: " . $rpcToken);

    $ch = curl_init($fullUrl);
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
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception('Invalid JSON response from OVP API: ' . json_last_error_msg());
    }

    return [
      'items' => $data['items'] ?? [],
      'totalResults' => $data['numFound'] ?? $data['totalResults'] ?? 0,
      'offset' => $offset,
      'limit' => $limit,
    ];
  }

  /**
   * Get authenticated thumbnail URL for a MediaClip.
   *
   * @param int $mediaclipId
   *   The MediaClip ID.
   * @param string $publication
   *   The publication name.
   *
   * @return string
   *   The authenticated thumbnail URL with rpctoken.
   */
  public function getAuthenticatedThumbnailUrl($mediaclipId, $publication) {
    $rpcToken = $this->generateRpcToken();
    $url = 'https://' . $publication . '.bbvms.com/mediaclip/' . $mediaclipId . '/spthumbnail/default/default';
    $url .= '?rpctoken=' . urlencode($rpcToken);
    return $url;
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

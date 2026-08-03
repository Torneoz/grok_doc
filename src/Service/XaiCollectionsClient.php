<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Client for xAI Management Collections and document operations.
 */
final class XaiCollectionsClient {

  use StringTranslationTrait;

  private const BASE_URL = 'https://management-api.x.ai/v1';

  public function __construct(
    private readonly ClientInterface $httpClient,
    TranslationInterface $string_translation,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Creates a Collection.
   */
  public function createCollection(string $api_key, string $name, string $description = ''): array {
    $this->assertApiKey($api_key);
    $name = trim($name);
    if ($name === '') {
      throw new \InvalidArgumentException('A Collection name is required.');
    }
    $payload = ['collection_name' => $name];
    if (trim($description) !== '') {
      $payload['collection_description'] = trim($description);
    }
    return $this->request('POST', '/collections', $api_key, ['json' => $payload]);
  }

  /**
   * Lists Collections visible to a Management API key.
   */
  public function listCollections(string $api_key): array {
    $this->assertApiKey($api_key);
    return $this->request('GET', '/collections', $api_key);
  }

  /**
   * Deletes a Collection and its remotely stored documents.
   */
  public function deleteCollection(string $api_key, string $collection_id): void {
    $this->assertCredentials($api_key, $collection_id);
    $this->request('DELETE', '/collections/' . rawurlencode($collection_id), $api_key);
  }

  /**
   * Uploads and attaches one document to a Collection.
   */
  public function uploadDocument(string $api_key, string $collection_id, string $path, string $filename, string $mime, array $fields = []): array {
    $this->assertCredentials($api_key, $collection_id);
    $handle = fopen($path, 'rb');
    if ($handle === FALSE) {
      throw new \RuntimeException((string) $this->t('The source document could not be opened.'));
    }
    $multipart = [
      ['name' => 'name', 'contents' => $filename],
      ['name' => 'content_type', 'contents' => $mime],
      ['name' => 'fields', 'contents' => Json::encode($fields)],
      ['name' => 'data', 'contents' => $handle, 'filename' => $filename],
    ];
    try {
      return $this->request('POST', '/collections/' . rawurlencode($collection_id) . '/documents', $api_key, [
        'multipart' => $multipart,
        'timeout' => $this->setting('upload_timeout', 300),
      ]);
    }
    finally {
      fclose($handle);
    }
  }

  /**
   * Retrieves one document's current indexing state.
   */
  public function getDocument(string $api_key, string $collection_id, string $file_id): array {
    $this->assertCredentials($api_key, $collection_id);
    if (!preg_match('/^file[_-][A-Za-z0-9-]+$/', $file_id)) {
      throw new \InvalidArgumentException('Invalid xAI file ID.');
    }
    return $this->request('GET', '/collections/' . rawurlencode($collection_id) . '/documents/' . rawurlencode($file_id), $api_key);
  }

  /**
   * Removes one document from a Collection.
   */
  public function deleteDocument(string $api_key, string $collection_id, string $file_id): void {
    $this->assertCredentials($api_key, $collection_id);
    $this->request('DELETE', '/collections/' . rawurlencode($collection_id) . '/documents/' . rawurlencode($file_id), $api_key);
  }

  /**
   * Sends an authenticated request and decodes a bounded JSON response. */
  private function request(string $method, string $path, string $api_key, array $options = []): array {
    $options['headers']['Authorization'] = 'Bearer ' . $api_key;
    $options += [
      'connect_timeout' => $this->setting('api_connect_timeout', 20),
      'timeout' => $this->setting('api_timeout', 120),
    ];
    try {
      $response = $this->httpClient->request($method, self::BASE_URL . $path, $options);
      $body = trim((string) $response->getBody());
      if ($body === '') {
        return [];
      }
      $decoded = Json::decode($body);
      if (!is_array($decoded)) {
        throw new \RuntimeException((string) $this->t('xAI returned an invalid Collections response.'));
      }
      return $decoded;
    }
    catch (RequestException $exception) {
      $status = $exception->getResponse()?->getStatusCode() ?? 0;
      $body = trim(strip_tags((string) $exception->getResponse()?->getBody()));
      $detail = $body === '' ? $exception->getMessage() : mb_substr($body, 0, 1000);
      throw new \RuntimeException((string) $this->t('xAI Collections API returned HTTP @status: @detail', [
        '@status' => $status ?: $this->t('connection error'),
        '@detail' => $detail,
      ]), $status, $exception);
    }
  }

  /**
   * Validates credentials and the remote Collection identifier. */
  private function assertCredentials(string $api_key, string $collection_id): void {
    $this->assertApiKey($api_key);
    if (!preg_match('/^collection_[A-Za-z0-9-]+$/', $collection_id)) {
      throw new \InvalidArgumentException('Invalid xAI collection ID.');
    }
  }

  /**
   * Validates a Management API key value without exposing it.
   */
  private function assertApiKey(string $api_key): void {
    if (trim($api_key) === '') {
      throw new \InvalidArgumentException('An xAI Management API key is required.');
    }
  }

  /**
   * Returns a positive integer setting or its safe fallback.
   */
  private function setting(string $name, int $fallback): int {
    $value = (int) $this->configFactory->get('grok_doc.settings')->get($name);
    return $value > 0 ? $value : $fallback;
  }

}

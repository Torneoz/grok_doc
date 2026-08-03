<?php

declare(strict_types=1);

namespace Drupal\Tests\grok_doc\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\grok_doc\Service\XaiCollectionsClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Tests the xAI Collections Management API boundary.
 */
final class XaiCollectionsClientTest extends TestCase {

  /**
   * Tests document upload fields use the xAI string-map object shape.
   */
  public function testUploadsDocumentWithObjectFields(): void {
    $path = tempnam(sys_get_temp_dir(), 'grok-doc-test-');
    file_put_contents($path, 'document');
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->expects(self::once())
      ->method('request')
      ->with(
        'POST',
        'https://management-api.x.ai/v1/collections/collection_123/documents',
        self::callback(static function (array $options): bool {
          $fields_are_valid = FALSE;
          foreach ($options['multipart'] as $part) {
            if ($part['name'] === 'fields') {
              $fields_are_valid = $part['contents'] === '{}';
            }
            if ($part['name'] === 'data' && is_resource($part['contents'])) {
              fclose($part['contents']);
            }
          }
          return $fields_are_valid;
        }),
      )
      ->willReturn(new Response(200, [], '{"file_id":"file_456","status":"PENDING"}'));
    try {
      $result = $this->createClient($http_client)->uploadDocument(
        'secret',
        'collection_123',
        $path,
        'document.txt',
        'text/plain',
      );
      self::assertSame('file_456', $result['file_id']);
    }
    finally {
      unlink($path);
    }
  }

  /**
   * Tests recovery when xAI reports identical content already uploaded.
   */
  public function testReusesExistingIdenticalDocument(): void {
    $path = tempnam(sys_get_temp_dir(), 'grok-doc-test-');
    file_put_contents($path, 'document');
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->method('request')->willThrowException(new RequestException(
      'Conflict',
      new Request('POST', 'https://management-api.x.ai/v1/collections/collection_123/documents'),
      new Response(409, [], 'A file with identical content already exists in this collection (file_id: file_existing-123).'),
    ));
    try {
      $result = $this->createClient($http_client)->uploadDocument(
        'secret',
        'collection_123',
        $path,
        'document.txt',
        'text/plain',
      );
      self::assertSame('file_existing-123', $result['file_id']);
      self::assertTrue($result['reused_existing']);
    }
    finally {
      unlink($path);
    }
  }

  /**
   * Tests Collection creation with the documented request shape.
   */
  public function testCreatesCollection(): void {
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->expects(self::once())
      ->method('request')
      ->with(
        'POST',
        'https://management-api.x.ai/v1/collections',
        self::callback(static fn (array $options): bool => $options['headers']['Authorization'] === 'Bearer secret'
          && $options['json'] === [
            'collection_name' => 'Product manuals',
            'collection_description' => 'Published manuals',
          ]),
      )
      ->willReturn(new Response(200, [], '{"collection_id":"collection_123"}'));

    $result = $this->createClient($http_client)->createCollection('secret', 'Product manuals', 'Published manuals');
    self::assertSame('collection_123', $result['collection_id']);
  }

  /**
   * Tests listing Collections.
   */
  public function testListsCollections(): void {
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->expects(self::once())
      ->method('request')
      ->with(
        'GET',
        'https://management-api.x.ai/v1/collections',
        self::callback(static fn (array $options): bool => $options['headers']['Authorization'] === 'Bearer secret'),
      )
      ->willReturn(new Response(200, [], '{"collections":[{"collection_id":"collection_123"}]}'));

    $result = $this->createClient($http_client)->listCollections('secret');
    self::assertSame('collection_123', $result['collections'][0]['collection_id']);
  }

  /**
   * Tests configured Management API timeouts.
   */
  public function testUsesConfiguredTimeouts(): void {
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->expects(self::once())
      ->method('request')
      ->with(
        'GET',
        'https://management-api.x.ai/v1/collections',
        self::callback(static fn (array $options): bool => $options['connect_timeout'] === 7
          && $options['timeout'] === 45),
      )
      ->willReturn(new Response(200, [], '{"collections":[]}'));

    $this->createClient($http_client, [
      'api_connect_timeout' => 7,
      'api_timeout' => 45,
    ])->listCollections('secret');
  }

  /**
   * Tests deleting a Collection.
   */
  public function testDeletesCollection(): void {
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->expects(self::once())
      ->method('request')
      ->with(
        'DELETE',
        'https://management-api.x.ai/v1/collections/collection_123',
        self::callback(static fn (array $options): bool => $options['headers']['Authorization'] === 'Bearer secret'),
      )
      ->willReturn(new Response(200));

    $this->createClient($http_client)->deleteCollection('secret', 'collection_123');
  }

  /**
   * Tests document status retrieval and authorization.
   */
  public function testGetsDocument(): void {
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->expects(self::once())
      ->method('request')
      ->with(
        'GET',
        'https://management-api.x.ai/v1/collections/collection_123/documents/file_456',
        self::callback(static fn (array $options): bool => $options['headers']['Authorization'] === 'Bearer secret'),
      )
      ->willReturn(new Response(200, [], '{"status":"DOCUMENT_STATUS_PROCESSED"}'));

    $result = $this->createClient($http_client)->getDocument('secret', 'collection_123', 'file_456');
    self::assertSame('DOCUMENT_STATUS_PROCESSED', $result['status']);
  }

  /**
   * Tests local rejection of malformed Collection identifiers.
   */
  public function testRejectsInvalidCollectionId(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->createClient($this->createMock(ClientInterface::class))->getDocument('secret', '../bad', 'file_456');
  }

  /**
   * Tests bounded upstream error reporting.
   */
  public function testReportsHttpFailure(): void {
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->method('request')->willThrowException(new RequestException(
      'Request failed',
      new Request('GET', 'https://management-api.x.ai/v1/collections/collection_123/documents/file_456'),
      new Response(429, [], 'rate limited'),
    ));
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('HTTP 429');
    $this->createClient($http_client)->getDocument('secret', 'collection_123', 'file_456');
  }

  /**
   * Creates the client with a minimal translation service.
   */
  private function createClient(ClientInterface $http_client, array $settings = []): XaiCollectionsClient {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')
      ->willReturnCallback(static fn (string $string, array $arguments = [], array $options = []): TranslatableMarkup => new TranslatableMarkup(
        $string,
        $arguments,
        $options,
        $translation,
      ));
    $translation->method('translateString')
      ->willReturnCallback(static fn (TranslatableMarkup $string): string => strtr(
        $string->getUntranslatedString(),
        $string->getArguments(),
      ));
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnCallback(static fn (string $name): mixed => $settings[$name] ?? NULL);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('grok_doc.settings')->willReturn($config);
    return new XaiCollectionsClient($http_client, $translation, $config_factory);
  }

}

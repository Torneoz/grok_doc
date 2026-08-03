<?php

declare(strict_types=1);

namespace Drupal\Tests\grok_doc\Unit;

use Drupal\grok_doc\GrokDocumentListBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Protects the document list from removed Drupal global helpers.
 */
final class DocumentListCompatibilityTest extends TestCase {

  /**
   * Ensures file sizes use Drupal's supported markup API.
   */
  public function testUsesByteSizeMarkup(): void {
    $source = file_get_contents((new \ReflectionClass(GrokDocumentListBuilder::class))->getFileName());
    self::assertIsString($source);
    self::assertStringContainsString('ByteSizeMarkup::create', $source);
    self::assertStringNotContainsString('format_size(', $source);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\grok_doc\Unit;

use Drupal\grok_doc\Utility\MetadataJson;
use PHPUnit\Framework\TestCase;

/**
 * Tests metadata JSON object validation.
 */
final class MetadataJsonTest extends TestCase {

  /**
   * Tests valid metadata objects, including the empty object regression.
   */
  public function testDecodesObjects(): void {
    self::assertSame([], MetadataJson::decodeObject('{}'));
    self::assertSame([
      'department' => 'legal',
      'year' => 2026,
    ], MetadataJson::decodeObject('{"department":"legal","year":2026}'));
    self::assertSame([
      'tags' => ['policy'],
      'source' => ['type' => 'media'],
    ], MetadataJson::decodeObject('{"tags":["policy"],"source":{"type":"media"}}'));
  }

  /**
   * Tests rejection of valid JSON values that are not objects.
   */
  public function testRejectsNonObjects(): void {
    foreach (['[]', '"metadata"', 'null', '{'] as $json) {
      try {
        MetadataJson::decodeObject($json);
        self::fail('Expected invalid metadata JSON to be rejected: ' . $json);
      }
      catch (\InvalidArgumentException) {
        self::assertTrue(TRUE);
      }
    }
  }

}

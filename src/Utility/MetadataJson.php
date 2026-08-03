<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Utility;

/**
 * Decodes and validates document metadata JSON objects.
 */
final class MetadataJson {

  /**
   * Decodes a JSON object to an associative array.
   *
   * @throws \InvalidArgumentException
   *   When the value is invalid JSON or its top level is not an object.
   */
  public static function decodeObject(string $json): array {
    try {
      $object = json_decode($json, FALSE, 512, JSON_THROW_ON_ERROR);
      if (!$object instanceof \stdClass) {
        throw new \InvalidArgumentException('The top-level JSON value must be an object.');
      }
      $decoded = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \InvalidArgumentException('The metadata is not valid JSON.', 0, $exception);
    }
    return is_array($decoded) ? $decoded : [];
  }

}

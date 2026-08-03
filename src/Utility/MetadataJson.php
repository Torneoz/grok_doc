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
    return self::normalizeFields(is_array($decoded) ? $decoded : []);
  }

  /**
   * Converts xAI Collection fields to a flat string map.
   *
   * @throws \InvalidArgumentException
   *   When a field contains an array, object, or null value.
   */
  public static function normalizeFields(array $fields): array {
    $normalized = [];
    foreach ($fields as $key => $value) {
      if (!is_scalar($value)) {
        throw new \InvalidArgumentException('Metadata values must be strings, numbers, or booleans.');
      }
      $normalized[(string) $key] = is_bool($value)
        ? ($value ? 'true' : 'false')
        : (string) $value;
    }
    return $normalized;
  }

  /**
   * Encodes fields as the JSON object required by the xAI Management API.
   */
  public static function encodeFields(array $fields): string {
    $object = new \stdClass();
    foreach (self::normalizeFields($fields) as $key => $value) {
      $object->{$key} = $value;
    }
    return json_encode($object, JSON_THROW_ON_ERROR);
  }

}

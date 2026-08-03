<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Service;

/**
 * Processes one registered Collection document.
 */
interface DocumentProcessorInterface {

  /**
   * Processes a document and returns complete, failed, or retry.
   */
  public function process(int $document_id): string;

}

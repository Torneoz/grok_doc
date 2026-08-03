<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Service;

use Drupal\Core\Queue\QueueFactory;

/**
 * Runs a bounded batch of Collection document ingestion queue items.
 */
final class IngestionQueueProcessor {

  /**
   * Constructs the bounded queue processor.
   */
  public function __construct(
    private readonly QueueFactory $queueFactory,
    private readonly CollectionDocumentManager $manager,
  ) {}

  /**
   * Processes at most the requested number of available items.
   *
   * @return array{processed: int, requeued: int}
   *   Counts for the completed bounded run.
   */
  public function process(int $limit): array {
    $queue = $this->queueFactory->get(CollectionDocumentManager::QUEUE);
    $processed = 0;
    $requeued = 0;
    for ($i = 0; $i < max(1, $limit) && ($item = $queue->claimItem(300)); $i++) {
      $result = $this->manager->process((int) ($item->data['document_id'] ?? 0));
      $queue->deleteItem($item);
      if ($result === 'retry') {
        $queue->createItem($item->data);
        $requeued++;
      }
      $processed++;
    }
    return ['processed' => $processed, 'requeued' => $requeued];
  }

}

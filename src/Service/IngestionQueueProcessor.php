<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Service;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\QueueInterface;
use Psr\Log\LoggerInterface;

/**
 * Runs a bounded batch of Collection document ingestion queue items.
 */
final class IngestionQueueProcessor {

  private const CLAIM_TIME = 300;

  private const MAX_BATCH_SIZE = 1000;

  private const RETRY_DELAY = 30;

  /**
   * Constructs the bounded queue processor.
   */
  public function __construct(
    private readonly QueueFactory $queueFactory,
    private readonly DocumentProcessorInterface $manager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Processes at most the requested number of available items.
   *
   * A retry is released back to the same queue rather than deleted and
   * recreated. If processing throws unexpectedly, the item is also released so
   * that a transient failure cannot strand it until the claim expires.
   *
   * @return array{processed: int, requeued: int, failed: int, discarded: int}
   *   Counts for the completed bounded run.
   */
  public function process(int $limit): array {
    $queue = $this->queueFactory->get(CollectionDocumentManager::QUEUE);
    $processed = 0;
    $requeued = 0;
    $failed = 0;
    $discarded = 0;
    $limit = min(self::MAX_BATCH_SIZE, max(1, $limit));

    for ($i = 0; $i < $limit; $i++) {
      try {
        $item = $queue->claimItem(self::CLAIM_TIME);
      }
      catch (\Throwable $exception) {
        $this->logger->error('Unable to claim a Collection ingestion queue item: @message', [
          '@message' => $exception->getMessage(),
        ]);
        $failed++;
        break;
      }
      if ($item === FALSE) {
        break;
      }

      $document_id = is_array($item->data) ? (int) ($item->data['document_id'] ?? 0) : 0;
      if ($document_id <= 0) {
        $this->deleteInvalidItem($queue, $item);
        $discarded++;
        continue;
      }

      try {
        $result = $this->manager->process($document_id);
        if ($result === 'retry') {
          $this->postponeItem($queue, $item);
          $requeued++;
        }
        elseif (in_array($result, ['complete', 'failed'], TRUE)) {
          $queue->deleteItem($item);
          if ($result === 'failed') {
            $failed++;
          }
        }
        else {
          throw new \UnexpectedValueException(sprintf('Unknown ingestion result "%s".', $result));
        }
        $processed++;
      }
      catch (\Throwable $exception) {
        $failed++;
        try {
          $this->postponeItem($queue, $item);
          $requeued++;
        }
        catch (\Throwable $release_exception) {
          $this->logger->critical('Unable to release Collection ingestion queue item @item after a processing failure: @message', [
            '@item' => $item->item_id ?? 'unknown',
            '@message' => $release_exception->getMessage(),
          ]);
        }
        $this->logger->error('Unexpected failure processing Collection document @id: @message', [
          '@id' => $document_id,
          '@message' => $exception->getMessage(),
        ]);
      }
    }
    return [
      'processed' => $processed,
      'requeued' => $requeued,
      'failed' => $failed,
      'discarded' => $discarded,
    ];
  }

  /**
   * Makes an item available for a later run, with a delay where supported.
   */
  private function postponeItem(QueueInterface $queue, object $item): void {
    $released = $queue instanceof DelayableQueueInterface
      ? $queue->delayItem($item, self::RETRY_DELAY)
      : $queue->releaseItem($item);
    if (!$released) {
      throw new \RuntimeException('The queue backend did not release the ingestion item.');
    }
  }

  /**
   * Deletes an unusable queue item without aborting the rest of the batch.
   */
  private function deleteInvalidItem(QueueInterface $queue, object $item): void {
    try {
      $queue->deleteItem($item);
      $this->logger->warning('Discarded a malformed Collection ingestion queue item.');
    }
    catch (\Throwable $exception) {
      $this->logger->error('Unable to discard a malformed Collection ingestion queue item: @message', [
        '@message' => $exception->getMessage(),
      ]);
    }
  }

}

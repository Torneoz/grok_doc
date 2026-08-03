<?php

declare(strict_types=1);

namespace Drupal\Tests\grok_doc\Unit;

use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\grok_doc\Service\DocumentProcessorInterface;
use Drupal\grok_doc\Service\IngestionQueueProcessor;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests bounded manual queue processing and recovery.
 */
final class IngestionQueueProcessorTest extends TestCase {

  /**
   * Tests retries are delayed without deleting and recreating the item.
   */
  public function testDelaysRetry(): void {
    $item = (object) ['item_id' => 7, 'data' => ['document_id' => 42]];
    $queue = $this->createMock(DelayableQueueInterface::class);
    $queue->expects(self::exactly(2))->method('claimItem')->willReturnOnConsecutiveCalls($item, FALSE);
    $queue->expects(self::once())->method('delayItem')->with($item, 30)->willReturn(TRUE);
    $queue->expects(self::never())->method('deleteItem');
    $processor = $this->createMock(DocumentProcessorInterface::class);
    $processor->expects(self::once())->method('process')->with(42)->willReturn('retry');

    self::assertSame(
      ['processed' => 1, 'requeued' => 1, 'failed' => 0, 'discarded' => 0],
      $this->createQueueProcessor($queue, $processor)->process(10),
    );
  }

  /**
   * Tests malformed items are removed and do not abort the batch.
   */
  public function testDiscardsMalformedItem(): void {
    $item = (object) ['item_id' => 8, 'data' => ['unexpected' => 42]];
    $queue = $this->createMock(DelayableQueueInterface::class);
    $queue->expects(self::exactly(2))->method('claimItem')->willReturnOnConsecutiveCalls($item, FALSE);
    $queue->expects(self::once())->method('deleteItem')->with($item);
    $processor = $this->createMock(DocumentProcessorInterface::class);
    $processor->expects(self::never())->method('process');

    self::assertSame(
      ['processed' => 0, 'requeued' => 0, 'failed' => 0, 'discarded' => 1],
      $this->createQueueProcessor($queue, $processor)->process(10),
    );
  }

  /**
   * Creates a processor around controlled queue dependencies.
   */
  private function createQueueProcessor(DelayableQueueInterface $queue, DocumentProcessorInterface $processor): IngestionQueueProcessor {
    $queue_factory = $this->createMock(QueueFactory::class);
    $queue_factory->method('get')->with('grok_doc_ingest')->willReturn($queue);
    return new IngestionQueueProcessor(
      $queue_factory,
      $processor,
      $this->createMock(LoggerInterface::class),
    );
  }

}

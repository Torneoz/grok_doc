<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\grok_doc\Service\IngestionQueueProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs a bounded number of ingestion queue items interactively.
 */
final class ProcessQueueForm extends ConfirmFormBase {

  /**
   * Constructs the manual queue form. */
  public function __construct(
    protected IngestionQueueProcessor $queueProcessor,
  ) {}

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('grok_doc.queue_processor'));
  }

  /**
   * {@inheritdoc} */
  public function getFormId(): string {
    return 'grok_doc_process_queue';
  }

  /**
   * {@inheritdoc} */
  public function getQuestion(): string {
    return (string) $this->t('Process queued Collection documents now?');
  }

  /**
   * {@inheritdoc} */
  public function getDescription(): string {
    return (string) $this->t('Cron normally processes this queue. A bounded manual run is useful while validating the alpha release.');
  }

  /**
   * {@inheritdoc} */
  public function getCancelUrl(): Url {
    return Url::fromRoute('entity.grok_doc_document.collection');
  }

  /**
   * {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $limit = max(1, (int) $this->config('grok_doc.settings')->get('queue_batch_size'));
    $result = $this->queueProcessor->process($limit);
    $this->messenger()->addStatus($this->t('Processed @count queue item(s); @requeued require further indexing checks.', [
      '@count' => $result['processed'],
      '@requeued' => $result['requeued'],
    ]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}

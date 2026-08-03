<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\FileInterface;
use Drupal\grok_doc\Service\CollectionDocumentManager;
use Drupal\grok_doc\Utility\MetadataJson;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queues one Drupal-managed file for Collection ingestion.
 */
final class AddDocumentForm extends FormBase {

  /**
   * Constructs the single-document form.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CollectionDocumentManager $manager,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('grok_doc.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'grok_doc_add_document';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    foreach ($this->entityTypeManager->getStorage('grok_doc_collection')->loadMultiple() as $collection) {
      if ($collection->isEnabled()) {
        $options[$collection->id()] = $collection->label() . ' — ' . $collection->getRemoteId();
      }
    }
    if ($options === []) {
      $form['empty'] = [
        '#markup' => $this->t('Register and enable a Grok collection before adding a document.'),
      ];
      return $form;
    }

    $requested_collection = (string) $this->getRequest()->query->get('collection', '');
    $form['collection'] = [
      '#type' => 'select',
      '#title' => $this->t('Collection'),
      '#options' => $options,
      '#default_value' => isset($options[$requested_collection]) ? $requested_collection : NULL,
      '#required' => TRUE,
    ];
    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Document'),
      '#multiple' => FALSE,
      '#upload_location' => 'temporary://grok_doc',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'txt md csv json html htm pdf docx pptx xlsx xml yaml yml'],
      ],
      '#description' => $this->t('Select one document. An identical file already registered in this Collection will not be queued again.'),
      '#required' => TRUE,
    ];
    $form['metadata'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Document metadata (JSON object)'),
      '#default_value' => '{}',
      '#description' => $this->t('Optional key/value fields for this document. These values override matching Collection defaults.'),
      '#rows' => 6,
      '#resizable' => 'vertical',
      '#attributes' => [
        'placeholder' => "{\n  \"department\": \"legal\",\n  \"year\": 2026\n}",
        'spellcheck' => 'false',
      ],
    ];
    $form['notice'] = [
      '#type' => 'item',
      '#title' => $this->t('Cost notice'),
      '#markup' => $this->t('Uploaded content incurs xAI file and Collection storage charges until it is removed. Processing occurs through Drupal queues.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add and queue document'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    try {
      MetadataJson::decodeObject((string) $form_state->getValue('metadata'));
    }
    catch (\Throwable) {
      $form_state->setErrorByName('metadata', $this->t('Metadata must be a valid JSON object.'));
    }

    $file = $this->loadFile($form_state);
    $max_file = (int) $this->config('grok_doc.settings')->get('max_file_bytes');
    if (!$file || $file->getSize() <= 0 || $file->getSize() > $max_file) {
      $form_state->setErrorByName('file', $this->t('The document exceeds the permitted file size.'));
    }
    $collection = $this->entityTypeManager->getStorage('grok_doc_collection')->load($form_state->getValue('collection'));
    if ($file && $collection && $file->getSize() > $collection->getMaxBatchBytes()) {
      $form_state->setErrorByName('file', $this->t('The document exceeds this Collection’s maximum batch size.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $file = $this->loadFile($form_state);
    $collection = $this->entityTypeManager->getStorage('grok_doc_collection')->load($form_state->getValue('collection'));
    if (!$file || !$collection) {
      $this->messenger()->addError($this->t('The document or Collection is no longer available.'));
      return;
    }

    try {
      $existing_file_count = $this->entityTypeManager->getStorage('grok_doc_document')->getQuery()
        ->accessCheck(FALSE)
        ->condition('collection_id', $collection->id())
        ->condition('file', $file->id())
        ->count()
        ->execute();
      $document = $this->manager->enqueue(
        $file,
        $collection,
        MetadataJson::decodeObject((string) $form_state->getValue('metadata')),
        (int) $this->currentUser->id(),
      );
      if ((int) $existing_file_count > 0
        || (int) $document->get('file')->target_id !== (int) $file->id()
        || $document->getStatus() !== 'pending') {
        $this->messenger()->addWarning($this->t('An identical document is already registered in this Collection; no new queue item was created.'));
      }
      else {
        $file->setPermanent();
        $file->save();
        $this->messenger()->addStatus($this->t('%file was added and queued for Collection ingestion.', [
          '%file' => $file->getFilename(),
        ]));
      }
      $form_state->setRedirect('entity.grok_doc_document.collection');
    }
    catch (\Throwable $exception) {
      $this->messenger()->addError($this->t('%file could not be queued: @message', [
        '%file' => $file->getFilename(),
        '@message' => $exception->getMessage(),
      ]));
    }
  }

  /**
   * Loads the selected managed file.
   */
  private function loadFile(FormStateInterface $form_state): ?FileInterface {
    $ids = array_values(array_filter(array_map('intval', (array) $form_state->getValue('file'))));
    return $ids ? $this->entityTypeManager->getStorage('file')->load(reset($ids)) : NULL;
  }

}

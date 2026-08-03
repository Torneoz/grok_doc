<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\grok_doc\Entity\GrokDocument;
use Drupal\grok_doc\Service\XaiCollectionsClient;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deletes a local Collection registration and optionally its remote Collection.
 */
final class GrokCollectionDeleteForm extends EntityConfirmFormBase {

  /**
   * Constructs the Collection deletion form.
   */
  public function __construct(
    protected KeyRepositoryInterface $keyRepository,
    protected XaiCollectionsClient $client,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->setEntityTypeManager($entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('key.repository'),
      $container->get('grok_doc.api_client'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc} */
  public function getQuestion(): string {
    return (string) $this->t('Delete the local registration for %label?', ['%label' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc} */
  public function getDescription(): string {
    return (string) $this->t('Local-only deletion leaves the xAI Collection and its documents unchanged. Remote deletion permanently removes the Collection and its stored documents from xAI.');
  }

  /**
   * {@inheritdoc} */
  public function getCancelUrl(): Url {
    return Url::fromRoute('entity.grok_doc_collection.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $remote_id = $this->entity->getRemoteId();
    $form['delete_remote'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Also permanently delete @id from xAI', ['@id' => $remote_id]),
      '#description' => $this->t('This destructive action cannot be undone and deletes every document stored in the remote Collection.'),
    ];
    $form['confirm_remote_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirm remote Collection ID'),
      '#description' => $this->t('Type @id to authorize remote deletion.', ['@id' => $remote_id]),
      '#states' => [
        'visible' => [':input[name="delete_remote"]' => ['checked' => TRUE]],
        'required' => [':input[name="delete_remote"]' => ['checked' => TRUE]],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('delete_remote')
      && trim((string) $form_state->getValue('confirm_remote_id')) !== $this->entity->getRemoteId()) {
      $form_state->setErrorByName('confirm_remote_id', $this->t('The confirmation must exactly match @id.', [
        '@id' => $this->entity->getRemoteId(),
      ]));
    }
  }

  /**
   * {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('delete_remote')) {
      try {
        $key = $this->keyRepository->getKey($this->entity->getManagementKeyId());
        $api_key = $key ? (string) $key->getKeyValue() : '';
        $this->client->deleteCollection($api_key, $this->entity->getRemoteId());
      }
      catch (\Throwable $exception) {
        $this->messenger()->addError($this->t('The xAI Collection was not deleted, so its local registration was retained: @message', [
          '@message' => $exception->getMessage(),
        ]));
        $form_state->setRebuild();
        return;
      }
      $storage = $this->entityTypeManager->getStorage('grok_doc_document');
      $document_ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('collection_id', $this->entity->id())
        ->execute();
      foreach ($storage->loadMultiple($document_ids) as $document) {
        $document->setStatus(GrokDocument::STATUS_REMOVED)->save();
      }
    }
    $this->entity->delete();
    $message = $form_state->getValue('delete_remote')
      ? $this->t('Deleted the xAI Collection and its local registration.')
      : $this->t('Deleted the local collection registration. The xAI Collection was not changed.');
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}

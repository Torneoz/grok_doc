<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\grok_doc\Service\XaiCollectionsClient;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists remote xAI Collections available to a Management API key.
 */
final class RemoteCollectionsForm extends FormBase {

  /**
   * Constructs the remote Collections form.
   */
  public function __construct(
    private readonly KeyRepositoryInterface $keyRepository,
    private readonly XaiCollectionsClient $client,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $settingsConfigFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('key.repository'),
      $container->get('grok_doc.api_client'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'grok_doc_remote_collections';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $keys = [];
    foreach ($this->keyRepository->getKeys() as $key) {
      $keys[$key->id()] = $key->label();
    }
    $selected_key = (string) ($form_state->get('management_key')
      ?? $this->settingsConfigFactory->get('grok_doc.settings')->get('default_management_key')
      ?? '');
    $form['management_key'] = [
      '#type' => 'select',
      '#title' => $this->t('xAI Management API key'),
      '#options' => $keys,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $selected_key,
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('List remote Collections'),
      '#button_type' => 'primary',
    ];

    $response = $form_state->get('remote_response');
    if (is_array($response)) {
      $registered = [];
      foreach ($this->entityTypeManager->getStorage('grok_doc_collection')->loadMultiple() as $collection) {
        $registered[$collection->getRemoteId()] = $collection;
      }
      $rows = [];
      foreach ($response['collections'] ?? [] as $remote) {
        if (!is_array($remote)) {
          continue;
        }
        $remote_id = (string) ($remote['collection_id'] ?? $remote['id'] ?? '');
        if (!preg_match('/^collection_[A-Za-z0-9-]+$/', $remote_id)) {
          continue;
        }
        $name = (string) ($remote['collection_name'] ?? $remote['name'] ?? $remote_id);
        $operation = isset($registered[$remote_id])
          ? Link::fromTextAndUrl($this->t('Edit registration'), $registered[$remote_id]->toUrl('edit-form'))->toRenderable()
          : Link::fromTextAndUrl($this->t('Register'), Url::fromRoute('entity.grok_doc_collection.add_form', [], [
            'query' => [
              'remote_id' => $remote_id,
              'label' => $name,
              'management_key' => $selected_key,
              'description' => (string) ($remote['collection_description'] ?? ''),
            ],
          ]))->toRenderable();
        $rows[] = [
          'name' => $name,
          'remote_id' => ['data' => ['#plain_text' => $remote_id]],
          'documents' => (int) ($remote['documents_count'] ?? 0),
          'created' => (string) ($remote['created_at'] ?? ''),
          'operation' => ['data' => $operation],
        ];
      }
      $form['collections'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Collection'),
          $this->t('xAI ID'),
          $this->t('Documents'),
          $this->t('Created'),
          $this->t('Operations'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('xAI returned no Collections for this Management API key.'),
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $key_id = (string) $form_state->getValue('management_key');
    try {
      $key = $this->keyRepository->getKey($key_id);
      $api_key = $key ? (string) $key->getKeyValue() : '';
      $form_state->set('remote_response', $this->client->listCollections($api_key));
      $form_state->set('management_key', $key_id);
      $form_state->setRebuild();
    }
    catch (\Throwable $exception) {
      $this->messenger()->addError($this->t('The remote Collections could not be listed: @message', [
        '@message' => $exception->getMessage(),
      ]));
      $form_state->set('management_key', $key_id);
      $form_state->setRebuild();
    }
  }

}

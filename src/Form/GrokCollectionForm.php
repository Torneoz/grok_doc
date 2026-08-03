<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\grok_doc\Service\XaiCollectionsClient;
use Drupal\grok_doc\Utility\MetadataJson;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds or edits a registered xAI Collection.
 */
final class GrokCollectionForm extends EntityForm {

  /**
   * Constructs the Collection form. */
  public function __construct(
    private readonly KeyRepositoryInterface $keyRepository,
    private readonly XaiCollectionsClient $client,
    private readonly ConfigFactoryInterface $settingsConfigFactory,
  ) {}

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('key.repository'),
      $container->get('grok_doc.api_client'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc} */
  public function form(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\grok_doc\Entity\GrokCollectionInterface $collection */
    $collection = $this->entity;
    $keys = [];
    foreach ($this->keyRepository->getKeys() as $key) {
      $keys[$key->id()] = $key->label();
    }
    $query = $this->getRequest()->query;
    $suggested_remote_id = $collection->isNew() ? trim((string) $query->get('remote_id', '')) : '';
    $suggested_label = $collection->isNew() ? trim((string) $query->get('label', '')) : '';
    $settings = $this->settingsConfigFactory->get('grok_doc.settings');
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $collection->label() ?: $suggested_label,
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $collection->id(),
      '#machine_name' => ['exists' => '\Drupal\grok_doc\Entity\GrokCollection::load'],
      '#disabled' => !$collection->isNew(),
    ];
    if ($collection->isNew()) {
      $form['collection_source'] = [
        '#type' => 'radios',
        '#title' => $this->t('Collection source'),
        '#options' => [
          'create' => $this->t('Create a new Collection in xAI'),
          'existing' => $this->t('Register an existing xAI Collection'),
        ],
        '#default_value' => $suggested_remote_id === '' ? 'create' : 'existing',
        '#required' => TRUE,
      ];
    }
    $form['remote_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('xAI collection ID'),
      '#description' => $this->t('For an existing Collection, enter its ID beginning with collection_. A newly created Collection receives its ID from xAI.'),
      '#default_value' => $collection->getRemoteId() ?: $suggested_remote_id,
      '#required' => !$collection->isNew(),
      '#states' => $collection->isNew() ? [
        'visible' => [':input[name="collection_source"]' => ['value' => 'existing']],
        'required' => [':input[name="collection_source"]' => ['value' => 'existing']],
      ] : [],
    ];
    $form['management_key'] = [
      '#type' => 'select',
      '#title' => $this->t('xAI Management API key'),
      '#description' => $this->t('Use a dedicated least-privilege Management key. Creating Collections requires CreateCollection; ingestion requires AddFileToCollection.'),
      '#options' => $keys,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $collection->getManagementKeyId()
        ?: (string) $query->get('management_key', '')
        ?: (string) $settings->get('default_management_key'),
      '#required' => TRUE,
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $collection->get('description') ?: (string) $query->get('description', ''),
    ];
    $form['default_metadata'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default metadata (JSON object)'),
      '#default_value' => $collection->get('default_metadata') ?: '{}',
      '#description' => $this->t('Optional key/value fields applied to every imported document. Use <code>{}</code> for no defaults. Batch metadata overrides matching keys.'),
      '#rows' => 6,
      '#resizable' => 'vertical',
      '#attributes' => [
        'placeholder' => "{\n  \"department\": \"legal\",\n  \"year\": 2026\n}",
        'spellcheck' => 'false',
      ],
    ];
    $form['max_batch_bytes'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum import batch size in bytes'),
      '#default_value' => $collection->isNew()
        ? (int) ($settings->get('max_batch_bytes') ?? 524288000)
        : $collection->getMaxBatchBytes(),
      '#min' => 1,
      '#required' => TRUE,
    ];
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow document ingestion'),
      '#default_value' => $collection->isEnabled(),
    ];
    $form['searchable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Approve this collection for Grok search configurations'),
      '#default_value' => $collection->isSearchable(),
    ];
    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $remote_id = trim((string) $form_state->getValue('remote_id'));
    $creating = $this->entity->isNew() && $form_state->getValue('collection_source') === 'create';
    if (!$creating && !preg_match('/^collection_[A-Za-z0-9-]+$/', $remote_id)) {
      $form_state->setErrorByName('remote_id', $this->t('Enter a valid xAI collection ID beginning with collection_.'));
    }
    try {
      MetadataJson::decodeObject((string) $form_state->getValue('default_metadata'));
    }
    catch (\Throwable) {
      $form_state->setErrorByName('default_metadata', $this->t('Metadata must be a valid JSON object.'));
    }
  }

  /**
   * {@inheritdoc} */
  public function save(array $form, FormStateInterface $form_state): int {
    $created_remotely = $this->entity->isNew() && $form_state->getValue('collection_source') === 'create';
    if ($created_remotely) {
      try {
        $key = $this->keyRepository->getKey((string) $form_state->getValue('management_key'));
        $api_key = $key ? (string) $key->getKeyValue() : '';
        $response = $this->client->createCollection(
          $api_key,
          trim((string) $form_state->getValue('label')),
          trim((string) $form_state->getValue('description')),
        );
        $remote_id = (string) ($response['collection_id'] ?? $response['id'] ?? '');
        if (!preg_match('/^collection_[A-Za-z0-9-]+$/', $remote_id)) {
          throw new \RuntimeException('xAI did not return a valid Collection ID.');
        }
        $this->entity->set('remote_id', $remote_id);
      }
      catch (\Throwable $exception) {
        $this->messenger()->addError($this->t('The xAI Collection could not be created: @message', [
          '@message' => $exception->getMessage(),
        ]));
        $form_state->setRebuild();
        return SAVED_NEW;
      }
    }
    $status = parent::save($form, $form_state);
    $message = $created_remotely
      ? $this->t('Created the %label Collection in xAI and saved its local registration.', ['%label' => $this->entity->label()])
      : $this->t('Saved the %label collection registration.', ['%label' => $this->entity->label()]);
    $this->messenger()->addStatus($message);
    $form_state->setRedirect('entity.grok_doc_collection.collection');
    return $status;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\grok_doc\Service\XaiCollectionsClient;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures Grok Collections and its xAI Management API defaults.
 */
final class GrokDocSettingsForm extends ConfigFormBase {

  /**
   * Constructs the Grok Collections settings form.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly KeyRepositoryInterface $keyRepository,
    private readonly XaiCollectionsClient $client,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('key.repository'),
      $container->get('grok_doc.api_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'grok_doc_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['grok_doc.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('grok_doc.settings');
    $keys = [];
    foreach ($this->keyRepository->getKeys() as $key) {
      $keys[$key->id()] = $key->label();
    }

    $form['xai'] = [
      '#type' => 'details',
      '#title' => $this->t('xAI Management API'),
      '#open' => TRUE,
    ];
    $form['xai']['default_management_key'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Management API key'),
      '#description' => $this->t('An ordinary xAI Grok inference API key will not work here. Create a separate Management API key in the <a href=":url" target="_blank" rel="noopener noreferrer">xAI Console Management Keys page</a>, grant <code>AddFileToCollection</code> and any required Collections Endpoint permissions, store that secret as a Drupal Key, then select it here. Grok Collections stores only the Drupal Key reference. Collection forms use this default and may override it.', [
        ':url' => Url::fromUri('https://console.x.ai/team/default/settings/management-keys')->toString(),
      ]),
      '#options' => $keys,
      '#empty_option' => $this->t('- No default -'),
      '#default_value' => (string) $config->get('default_management_key'),
      '#required' => TRUE,
    ];
    $form['xai']['api_connect_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Connection timeout'),
      '#description' => $this->t('Seconds allowed to establish a connection to the xAI Management API.'),
      '#default_value' => (int) ($config->get('api_connect_timeout') ?? 20),
      '#min' => 1,
      '#max' => 120,
      '#required' => TRUE,
    ];
    $form['xai']['api_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('API request timeout'),
      '#description' => $this->t('Seconds allowed for Collection listing, creation, status, and deletion requests.'),
      '#default_value' => (int) ($config->get('api_timeout') ?? 120),
      '#min' => 1,
      '#max' => 600,
      '#required' => TRUE,
    ];
    $form['xai']['upload_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Document upload timeout'),
      '#description' => $this->t('Seconds allowed for an individual document upload.'),
      '#default_value' => (int) ($config->get('upload_timeout') ?? 300),
      '#min' => 1,
      '#max' => 3600,
      '#required' => TRUE,
    ];

    $form['connection'] = [
      '#type' => 'details',
      '#title' => $this->t('Test connection'),
      '#description' => $this->t('Performs a read-only Collections list request using the selected Management API key. No remote data is changed.'),
      '#open' => TRUE,
      '#attributes' => ['id' => 'grok-collections-connection-wrapper'],
      '#states' => [
        'visible' => [
          ':input[name="default_management_key"]' => ['!value' => ''],
        ],
      ],
    ];
    $form['connection']['test_connection'] = [
      '#type' => 'submit',
      '#name' => 'test_connection',
      '#value' => $this->t('Test Collections connection'),
      '#submit' => ['::testConnection'],
      '#limit_validation_errors' => [
        ['default_management_key'],
      ],
      '#ajax' => [
        'callback' => '::connectionAjax',
        'wrapper' => 'grok-collections-connection-wrapper',
        'progress' => ['type' => 'throbber'],
      ],
    ];
    if ($status = $form_state->get('grok_collections_connection_status')) {
      $form['connection']['status'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['messages', $status['type'] === 'error' ? 'messages--error' : 'messages--status'],
        ],
        'message' => ['#plain_text' => $status['message']],
      ];
    }

    $form['ingestion'] = [
      '#type' => 'details',
      '#title' => $this->t('Document ingestion'),
      '#open' => TRUE,
    ];
    $form['ingestion']['max_file_bytes'] = $this->numberElement(
      $this->t('Maximum file size in bytes'),
      $this->t('Reject individual files larger than this value before they are queued.'),
      (int) ($config->get('max_file_bytes') ?? 104857600),
      1,
    );
    $form['ingestion']['max_batch_bytes'] = $this->numberElement(
      $this->t('Default maximum batch size in bytes'),
      $this->t('Used as the default when a new Collection registration is created. Each Collection can override it.'),
      (int) ($config->get('max_batch_bytes') ?? 524288000),
      1,
    );
    $form['ingestion']['max_attempts'] = $this->numberElement(
      $this->t('Maximum ingestion attempts'),
      $this->t('A document is marked failed after this many unsuccessful queue attempts.'),
      (int) ($config->get('max_attempts') ?? 5),
      1,
      100,
    );
    $form['ingestion']['queue_batch_size'] = $this->numberElement(
      $this->t('Manual queue batch size'),
      $this->t('Maximum queue items processed during one manual run.'),
      (int) ($config->get('queue_batch_size') ?? 10),
      1,
      1000,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('grok_doc.settings')
      ->set('default_management_key', (string) $form_state->getValue('default_management_key'))
      ->set('api_connect_timeout', (int) $form_state->getValue('api_connect_timeout'))
      ->set('api_timeout', (int) $form_state->getValue('api_timeout'))
      ->set('upload_timeout', (int) $form_state->getValue('upload_timeout'))
      ->set('max_file_bytes', (int) $form_state->getValue('max_file_bytes'))
      ->set('max_batch_bytes', (int) $form_state->getValue('max_batch_bytes'))
      ->set('max_attempts', (int) $form_state->getValue('max_attempts'))
      ->set('queue_batch_size', (int) $form_state->getValue('queue_batch_size'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Tests the selected Management API key without changing remote data.
   */
  public function testConnection(array &$form, FormStateInterface $form_state): void {
    try {
      $key = $this->keyRepository->getKey((string) $form_state->getValue('default_management_key'));
      $api_key = $key ? (string) $key->getKeyValue() : '';
      $response = $this->client->listCollections($api_key);
      $count = count((array) ($response['collections'] ?? []));
      $form_state->set('grok_collections_connection_status', [
        'type' => 'status',
        'message' => (string) $this->formatPlural(
          $count,
          'Connection successful. One xAI Collection is available.',
          'Connection successful. @count xAI Collections are available.',
        ),
      ]);
    }
    catch (\Throwable $exception) {
      $form_state->set('grok_collections_connection_status', [
        'type' => 'error',
        'message' => (string) $this->t('Connection failed: @message', [
          '@message' => $exception->getMessage(),
        ]),
      ]);
    }
    $form_state->setRebuild();
  }

  /**
   * Returns the AJAX-rebuilt connection controls.
   */
  public function connectionAjax(array &$form, FormStateInterface $form_state): array {
    return $form['connection'];
  }

  /**
   * Builds a required integer form element.
   */
  private function numberElement(mixed $title, mixed $description, int $default, int $min, ?int $max = NULL): array {
    $element = [
      '#type' => 'number',
      '#title' => $title,
      '#description' => $description,
      '#default_value' => $default,
      '#min' => $min,
      '#required' => TRUE,
    ];
    if ($max !== NULL) {
      $element['#max'] = $max;
    }
    return $element;
  }

}

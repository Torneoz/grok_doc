<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures Grok Documents and its xAI Management API defaults.
 */
final class GrokDocSettingsForm extends ConfigFormBase {

  /**
   * Constructs the Grok Documents settings form.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly KeyRepositoryInterface $keyRepository,
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
      '#description' => $this->t('Stored as a Drupal Key reference, never as a secret in Grok Documents configuration. Collection forms use this as their default; each Collection can override it.'),
      '#options' => $keys,
      '#empty_option' => $this->t('- No default -'),
      '#default_value' => (string) $config->get('default_management_key'),
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

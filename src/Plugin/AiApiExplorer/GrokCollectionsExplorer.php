<?php

declare(strict_types=1);

namespace Drupal\grok_doc\Plugin\AiApiExplorer;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;
use Drupal\ai_api_explorer\ExplorerHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a Grok Collections Search explorer.
 */
#[AiApiExplorer(
  id: 'grok_collections_search',
  title: new TranslatableMarkup('Grok Collections Search Explorer'),
  description: new TranslatableMarkup('Search approved xAI Collections with Grok and inspect the answer, citations, and hosted-tool results.'),
)]
final class GrokCollectionsExplorer extends AiApiExplorerPluginBase {

  /**
   * Constructs the Grok Collections Search explorer.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RequestStack $requestStack,
    AiProviderFormHelper $aiProviderHelper,
    ExplorerHelper $explorerHelper,
    AiProviderPluginManager $providerManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $requestStack, $aiProviderHelper, $explorerHelper, $providerManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('ai.form_helper'),
      $container->get('ai_api_explorer.helper'),
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    try {
      $provider = $this->providerManager->createInstance('grok');
      return $provider->isUsable('chat') && $this->collectionOptions() !== [];
    }
    catch (\Throwable) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccess(AccountInterface $account): bool {
    return $account->hasPermission('access ai prompt')
      && $account->hasPermission('use grok collections explorer');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = $this->getFormTemplate($form, 'grok-collections-response');
    $permitted = (bool) $this->configFactory->get('grok.settings')->get('hosted_tools.file_search');

    if (!$permitted) {
      $form['left']['permission_warning'] = [
        '#type' => 'item',
        '#title' => $this->t('Collections Search is not permitted'),
        '#description' => $this->t('Enable <em>Permit Collections Search</em> in the Grok Integration provider settings before running this Explorer.'),
      ];
    }

    $form['left']['collections'] = [
      '#type' => 'select',
      '#title' => $this->t('Collections'),
      '#description' => $this->t('Only enabled registrations approved for search are available.'),
      '#options' => $this->collectionOptions(),
      '#multiple' => TRUE,
      '#required' => TRUE,
    ];
    $form['left']['question'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Question'),
      '#default_value' => $this->t('Summarize the most relevant information in these documents and cite the sources used.'),
      '#required' => TRUE,
      '#rows' => 6,
    ];
    $form['left']['maximum_results'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum collection results'),
      '#default_value' => 10,
      '#min' => 1,
      '#max' => 50,
      '#required' => TRUE,
    ];
    $form['left']['model'] = [
      '#type' => 'select',
      '#title' => $this->t('Grok model'),
      '#options' => $this->modelOptions(),
      '#default_value' => $this->defaultModel(),
      '#required' => TRUE,
    ];
    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search Collections'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'grok-collections-response',
      ],
      '#disabled' => !$permitted,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      $allowed = $this->collectionOptions();
      $selected = array_values(array_intersect(
        array_keys($allowed),
        array_map('strval', (array) $form_state->getValue('collections')),
      ));
      if ($selected === []) {
        throw new \InvalidArgumentException((string) $this->t('Select at least one approved Collection.'));
      }

      $provider = $this->providerManager->createInstance('grok');
      $provider->setConfiguration([
        'use_responses_api' => TRUE,
        'file_search' => TRUE,
        'collection_ids' => implode(',', $selected),
        'file_search_max_results' => max(1, min(50, (int) $form_state->getValue('maximum_results'))),
      ]);
      $input = new ChatInput([
        new ChatMessage('user', trim((string) $form_state->getValue('question'))),
      ]);
      $output = $provider->chat($input, (string) $form_state->getValue('model'), [
        'grok_collections_search',
        'ai_api_explorer',
      ]);

      $message = $output->getNormalized();
      $metadata = (array) $output->getMetadata();
      $form['right']['response']['#context']['ai_response'] = [
        'answer_heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Answer'),
        ],
        'answer' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#plain_text' => $message->getText(),
          '#attributes' => ['style' => 'white-space: pre-wrap'],
        ],
        'citations' => $this->buildCitations((array) ($metadata['citations'] ?? [])),
        'results' => $this->buildToolResults((array) ($metadata['tool_usage'] ?? [])),
        'details' => [
          '#type' => 'details',
          '#title' => $this->t('Response metadata'),
          '#open' => FALSE,
          'value' => [
            '#type' => 'html_tag',
            '#tag' => 'pre',
            '#plain_text' => Json::encode($metadata),
          ],
        ],
      ];
    }
    catch (\Throwable $exception) {
      $form['right']['response']['#context']['ai_response'] = [
        'error' => [
          '#type' => 'inline_template',
          '#template' => '{{ error|raw }}',
          '#context' => [
            'error' => $this->explorerHelper->renderException($exception),
          ],
        ],
      ];
    }
    $form_state->setRebuild();
    return $form['right'];
  }

  /**
   * Returns approved remote Collection IDs and labels.
   */
  private function collectionOptions(): array {
    $options = [];
    foreach ($this->entityTypeManager->getStorage('grok_doc_collection')->loadMultiple() as $collection) {
      if ($collection->isEnabled() && $collection->isSearchable()) {
        $remote_id = $collection->getRemoteId();
        if ($remote_id !== '') {
          $options[$remote_id] = $collection->label() . ' (' . $remote_id . ')';
        }
      }
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
  }

  /**
   * Returns configured Grok chat models.
   */
  private function modelOptions(): array {
    try {
      return $this->providerManager->createInstance('grok')->getConfiguredModels('chat');
    }
    catch (\Throwable) {
      return [];
    }
  }

  /**
   * Returns the configured default Grok chat model when available.
   */
  private function defaultModel(): string {
    $default = $this->providerManager->getDefaultProviderForOperationType('chat');
    return ($default['provider_id'] ?? '') === 'grok' ? (string) ($default['model_id'] ?? '') : '';
  }

  /**
   * Builds citation links from normalized Grok metadata.
   */
  private function buildCitations(array $citations): array {
    $items = [];
    foreach (array_unique(array_filter($citations, 'is_string')) as $citation) {
      if (!str_starts_with($citation, 'https://') && !str_starts_with($citation, 'http://')) {
        continue;
      }
      $items[] = [
        '#type' => 'link',
        '#title' => $citation,
        '#url' => Url::fromUri($citation),
        '#attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer'],
      ];
    }
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Citations'),
      '#items' => $items,
      '#access' => $items !== [],
    ];
  }

  /**
   * Builds an inspectable view of xAI hosted-tool results.
   */
  private function buildToolResults(array $tool_usage): array {
    return [
      '#type' => 'details',
      '#title' => $this->t('Collection search results'),
      '#open' => TRUE,
      '#access' => $tool_usage !== [],
      'value' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#plain_text' => json_encode($tool_usage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]',
      ],
    ];
  }

}

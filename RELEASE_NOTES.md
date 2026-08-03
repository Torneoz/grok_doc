# Grok Collections 1.0.0-alpha1

The first alpha of Grok Collections (`grok_doc`) provides standalone xAI
Collection administration and queued document ingestion for Grok Integration.

## Included

- Create xAI Collections or register existing Collection IDs in Drupal.
- Store Management API credentials through Drupal Key.
- List remote Collections and explicitly approve registrations for search.
- Upload multiple Drupal-managed files with metadata, size limits, SHA-256
  duplicate detection, queues, retries, and indexing-state tracking.
- Delete local registrations independently from explicitly confirmed remote
  Collection deletion.
- Configure API timeouts, ingestion limits, retry attempts, and manual queue
  batch sizes.
- Use the dedicated **Grok Collections Search Explorer** from Drupal AI's
  standard Explorer selection.

## Explorer

The Explorer uses Drupal AI's `AiApiExplorerPluginBase` and automatic Explorer
route/menu discovery. It appears when Grok is configured and at least one
enabled Collection registration is approved for search.

Trusted users can select one or more approved Collections, a Grok model, a
result limit, and a question. It displays the synthesized answer, citations,
xAI hosted-tool results, and normalized response metadata. Access requires both
Drupal AI's **Access AI prompt** permission and the restricted **Use the Grok
Collections Search Explorer** permission.

Collections Search must also be permitted in Grok Integration. Until it is,
the Explorer remains visible but disables submission and explains the required
setting.

## Requirements

- PHP 8.1 or later
- Drupal 10.6 or Drupal 11.2+
- Grok Integration 1.0 beta
- Key 1.22 or later
- Optional Drupal AI API Explorer module for the search Explorer UI

## Installation

```bash
composer config repositories.grok_doc vcs https://github.com/Torneoz/grok_doc.git
composer require 'torneoz/grok_doc:^1.0@alpha'
drush en grok_doc -y
```

This is an alpha release. Test with non-sensitive documents and a temporary
Collection before using production data. xAI Collection storage, document
operations, searches, and model tokens may be billed separately.

# Grok Collections 1.0.0-alpha2

Alpha 2 improves the reliability and usability of xAI Collection document
ingestion while retaining the explicit, least-privilege controls introduced in
the first alpha.

## Highlights

- Adds **Add document** to the Collection Documents list for queueing a single
  Drupal-managed file with optional metadata.
- Adds a bounded **Process the ingestion queue immediately** option to single
  and bulk upload forms. Outstanding indexing checks continue through cron.
- Recovers the existing xAI file ID after an identical-content response so an
  interrupted request can resume indexing without duplicating remote content.
- Sends empty metadata as the xAI-required JSON object `{}` rather than `[]` and
  validates metadata values before upload.
- Fixes injected form services after AJAX rebuilds and replaces the removed
  legacy file-size formatter with Drupal's supported `ByteSizeMarkup` API.
- Handles HTTP clients that close consumed multipart streams after a successful
  upload response.

## Existing capabilities

- Create xAI Collections or register existing Collection IDs in Drupal.
- Store a separate xAI Management API credential through Drupal Key.
- List, approve, and explicitly delete remote Collections.
- Queue documents with size limits, SHA-256 duplicate detection, retries, and
  indexing-state tracking.
- Test approved Collections through the Grok Collections Search Explorer when
  Drupal AI's optional API Explorer module is enabled.

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

This remains an alpha release. Test with non-sensitive documents and a
temporary Collection before using production data. xAI Collection storage,
document operations, searches, and model tokens may be billed separately.

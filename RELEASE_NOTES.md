# Grok Collections 1.0.0-alpha3

Alpha 3 strengthens installation diagnostics, uninstall cleanup, and document
queue processing while preserving the existing Collection-management workflow.

## Highlights

- Makes Drupal's status report resilient when entity storage or a queue backend
  is temporarily unavailable.
- Shows failed ingestion records and pending queue depth in the status report.
- Cleans up the ingestion queue during uninstall without making an unavailable
  backend block module removal.
- Prevents stranded pending records by rolling back document creation when
  queue insertion fails.
- Delays retries where the queue backend supports it and releases items safely
  after unexpected failures instead of risking loss or repeated hot-looping.
- Removes malformed queue items without aborting the rest of a manual batch and
  reports processed, requeued, failed, and discarded counts separately.
- Adds regression tests around retry and malformed queue-item behavior.

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

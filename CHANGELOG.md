# Changelog

## 1.0.0-alpha1

- Initial standalone project extraction from the Grok AI Provider repository.
- Registers existing xAI Collections through Drupal configuration entities.
- Adds queued multi-file ingestion, indexing-state tracking, SHA-256 duplicate
  detection, batch metadata, Management-key configuration, and import limits.
- Adds dedicated permissions and an explicit search-approval flag.
- Adds Management API-backed Collection creation and listing to the Drupal UI.
- Adds separately confirmed remote Collection deletion while retaining
  non-destructive local-only registration deletion as the default.
- Adds a central configuration form for the default Drupal Key reference, xAI
  API timeouts, ingestion limits, retry attempts, and manual queue batch size.
- Adds a Grok Collections Search Explorer plugin to Drupal AI's standard
  Explorer selection, restricted to approved Collection registrations and a
  dedicated permission.
- Displays Grok's synthesized answer, citations, hosted-tool results, and
  response metadata with safe output rendering.
- Improves JSON metadata entry and validation for Collections and bulk imports.

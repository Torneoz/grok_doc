# Changelog

## 1.0.0-alpha2 - 2026-08-03

- Adds a bounded **Process the ingestion queue immediately** control to the
  single-document and bulk-import forms, backed by the same reusable processor
  as the manual queue form.
- Fixes empty document metadata being uploaded as `[]`; xAI Collection fields
  are now sent as the required JSON object with string values.
- Handles HTTP clients that close consumed multipart streams without masking a
  successful xAI upload response.
- Recovers the existing xAI file ID from an identical-content conflict so an
  interrupted upload can resume indexing without duplicating remote content.
- Replaces the removed legacy `format_size()` helper with Drupal's
  `ByteSizeMarkup` API on the Collection Documents list.
- Fixes AJAX form rebuilds losing injected services, including managed-file
  uploads on the single and bulk document forms.
- Adds an **Add document** action to the Collection Documents list for
  selecting a Collection, uploading one file, adding optional metadata, and
  queueing ingestion through the existing duplicate and size safeguards.

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

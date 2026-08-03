# Grok Collections (`grok_doc`)

Grok Collections is an independent Drupal module for xAI Collection management
and bulk document ingestion. It complements the Grok AI Provider: `grok` performs AI
operations and Collections Search, while `grok_doc` manages the documents that
make those searches useful.

## Alpha scope

- Create xAI Collections or register existing `collection_...` identifiers as
  Drupal configuration.
- List remote Collections available to a selected Management API key.
- Delete local registrations independently, or explicitly delete a remote
  Collection and all of its stored documents.
- Store xAI Management credentials through Drupal Key.
- Upload multiple Drupal-managed documents with batch metadata.
- Enforce 100 MiB per-file and configurable per-batch limits.
- Detect identical documents by SHA-256 within a Collection.
- Upload and index asynchronously through Drupal's Queue API.
- Track local, remote, indexing, ready, and failed states.
- Mark registrations that are explicitly approved for Collections Search.
- Keep local registration deletion separate from destructive remote deletion,
  with an exact-ID confirmation for the remote operation.

## Installation

Install the project, create a least-privilege xAI Management API key in Drupal
Key, and grant only the upstream permissions required by the intended workflow.
Collection creation, listing, deletion, and document ingestion require their
corresponding Management API permissions.

```bash
composer require drupal/grok_doc:^1.0@alpha
```

```bash
drush en grok_doc
```

Visit **Configuration → AI → Grok collections** to create or register a
Collection. Use **List remote collections** to inspect and register Collections
visible to a selected key, then use **Bulk import**. Cron processes queued files;
administrators may also run a bounded queue batch from the process route during
alpha testing.

Use **Configuration → AI → Grok Collections settings** to select the default
Drupal Key used for xAI Management API operations and configure API timeouts,
file and batch limits, retry attempts, and manual queue batch size. The default
key and batch limit prepopulate new Collection registrations; Collection-level
values remain explicit overrides.

The settings page is listed under **AI → AI Platform Providers**. It requires a
separate xAI Management API key: the normal Grok inference key does not authorize
Collections management. Create the key in the xAI Console Management Keys page,
grant only the required Collections permissions, then store it through Drupal
Key.

## Security and cost notes

- A Management API key is more privileged than an ordinary inference key.
- Secrets are resolved from Drupal Key only for the requested Management API
  operation and are never stored in Collection configuration.
- Remote Collection deletion is opt-in, requires typing the exact remote ID,
  and permanently deletes every document in that Collection.
- xAI stores uploaded Files and Collection indexes until they are removed.
- Storage, downloads, searches, and model tokens can all be billed separately.
- Zero-data-retention configurations are not compatible with persistent
  Collections.
- Test with non-sensitive documents and a temporary Collection first.

## Known alpha limitations

- Remote Collection replacement workflows are not exposed.
- Media Library selection and directory-based Drush import are not included.
- Progress refresh is manual; indexing is eventually consistent.
- Metadata is accepted as a JSON object but is not yet validated against remote
  Collection field definitions.

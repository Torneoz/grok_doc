# Grok Collections (`grok_doc`)

Grok Collections is an independent Drupal module for xAI Collection management
and bulk document ingestion. It complements Grok Integration: `grok` performs AI
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
- Test approved Collections in a dedicated Grok Collections Search Explorer,
  with the synthesized answer, citations, hosted-tool results, and metadata.
- Keep local registration deletion separate from destructive remote deletion,
  with an exact-ID confirmation for the remote operation.

## Installation

Add the GitHub repository to the Drupal project and install the alpha release:

```bash
composer config repositories.grok_doc vcs https://github.com/Torneoz/grok_doc.git
composer require 'torneoz/grok_doc:^1.0@alpha'
```

```bash
drush en grok_doc -y
```

## Configuration

Grok Collections appears under **Configuration → AI → AI Platform Providers →
Grok Collections settings**. Select the default Drupal Key used for xAI
Management API operations and configure API timeouts, file and batch limits,
retry attempts, and manual queue batch size. The key is required. The default
key and batch limit prepopulate new Collection registrations; Collection-level
values remain explicit overrides.

The normal Grok inference API key does not authorize Collections management.
Create a separate Management API key on the
[xAI Console Management Keys page](https://console.x.ai/team/default/settings/management-keys),
grant `AddFileToCollection` and only the additional Collections Endpoint
permissions required by the intended workflow, then store the secret through
Drupal Key. The [xAI Collections API guide](https://docs.x.ai/developers/files/collections/api)
documents the current permissions and Management API workflow.

Use **Test Collections connection** before saving. The test makes a read-only
Collections list request and reports either the number of accessible remote
Collections or the upstream error; it never changes remote data.

## Grok Collections Search Explorer

When Drupal AI's optional **AI API Explorer** module is enabled, Grok
Collections adds **Grok Collections Search Explorer** to the standard Explorer
selection at `/admin/config/ai/explorers`. The Explorer appears when Grok is
configured and at least one enabled Collection registration is approved for
search.

Grant **Use the Grok Collections Search Explorer** together with Drupal AI's
**Access AI prompt** permission to trusted roles. Searches can incur xAI
Collections Search and model-token charges. Users select one or more approved
Collections, a Grok chat model, a result limit, and a question. The response
shows Grok's answer, normalized citations, hosted-tool results, and response
metadata without rendering model output as trusted HTML.

The Explorer remains visible when Collections Search is not yet permitted in
Grok Integration, but disables submission and explains the required provider
setting.

## Collection management and ingestion

Open **Grok collections** beneath the settings menu to create or register a
Collection. Use **List remote collections** to inspect and register Collections
visible to the selected key, then use **Bulk import**. Cron processes queued
files; administrators may also run a bounded queue batch manually.

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

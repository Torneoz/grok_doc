# Security policy

## Supported releases

Security fixes are provided for the latest tagged release on the 1.x branch.
Pre-release users should upgrade to the newest alpha or beta before reporting
an issue that may already have been corrected. Alpha releases are not covered
by Drupal's security advisory policy.

## Reporting a vulnerability

Do not open a public issue containing API keys, prompts, uploaded media,
customer data, internal hostnames, or exploit details. Use GitHub's private
security-advisory reporting for the Torneoz/grok_doc repository. If
private reporting is unavailable, contact the project maintainers through the
private contact method published by the Torneoz project.

Include the affected module, Drupal, Grok, PHP, and HTTP-client versions;
reproduction steps; and the smallest sanitized log extract needed to understand
the issue. Revoke any credential that may have been disclosed.

## Security boundaries

Administrators select an xAI Management API key capable of adding documents to
Collections. The key is resolved only by server-side queue processing and must
never be exposed to browser clients or logs. Collection registration and import
permissions do not replace access control on source Drupal files or Media.

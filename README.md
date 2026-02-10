# Invisible Form Save & Resume

A WordPress plugin that invisibly auto-saves in-progress forms and restores them through secure magic links.

## Features

- Auto-save on `input`, `blur`, and `change` events with configurable frequency.
- Resume via secure, expiring magic links sent to the form email field.
- Restore draft state when a user returns with `?ifsr_resume=<token>`.
- Works without authenticated users.
- GDPR-friendly controls:
  - Required consent checkbox option.
  - Configurable draft expiry and retention cleanup windows.
- Support layers:
  - Native HTML forms.
  - Gutenberg block forms (`data-wp-block-form` attribute detection).
  - Adapter filter for third-party plugin integrations (`ifsr_form_adapters`).

## Plugin Structure

```text
invisible-form-save-resume.php
includes/
  class-ifsr-db.php
  class-ifsr-token.php
  class-ifsr-rest-controller.php
  class-ifsr-cron.php
  class-ifsr-admin.php
  class-ifsr-form-adapter-interface.php
  class-ifsr-adapter-native-html.php
  class-ifsr-adapter-gutenberg.php
assets/js/
  ifsr-client.js
templates/
  admin-page.php
```

## Data Model

Two custom tables are created on activation:

1. `wp_ifsr_submissions`
   - Stores form fingerprint, source adapter, email, consent, JSON state, and expiry timestamps.
2. `wp_ifsr_tokens`
   - Stores one-time token hashes linked to submission IDs with expiry and `used_at` columns.

Only hashed tokens are persisted, never raw tokens.

## REST API

Namespace: `ifsr/v1`

- `POST /save` — persist/update draft + issue a token + send resume email.
- `GET /restore?token=...` — validate token and return state.
- `POST /cleanup` — admin-only cleanup endpoint for expired/old drafts.

## Example Magic-Link Flow

1. Visitor starts filling a form.
2. JS tracks form state and calls `POST /ifsr/v1/save` periodically.
3. Server upserts submission row and creates a one-time token:
   - raw token generated server-side
   - token hash stored in DB
4. Email is sent to the visitor with `https://example.com/?ifsr_resume=<raw-token>`.
5. Visitor opens link later.
6. JS detects `ifsr_resume`, calls `GET /ifsr/v1/restore`.
7. Server resolves token hash, validates expiration + unused status, returns saved state, and marks token used.
8. JS hydrates form fields and visitor resumes filling.

## Security Notes

- REST save endpoint validates `X-WP-Nonce`.
- Inputs are sanitized (`sanitize_text_field`, `sanitize_email`, `sanitize_key`, etc.).
- SQL is parameterized with `$wpdb->prepare`.
- Tokens are HMAC-SHA256 hashes with `wp_salt('auth')`.
- Cleanup removes expired/aged rows via WP-Cron and admin endpoint.

## Admin UI

Menu: **Form Save & Resume**

- Configure:
  - Auto-save frequency (seconds)
  - Resume expiry (hours)
  - Data retention (days)
  - Consent requirement toggle
- View abandoned entries.
- Resend resume links.
- Delete submissions manually.

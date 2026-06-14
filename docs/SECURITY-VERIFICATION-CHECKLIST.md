# UltraCache Pro Security Verification Checklist

Use this checklist on staging before production rollout. It is intentionally role-based so audits can verify the last missing security point without changing public APIs.

## Required role and nonce matrix

| Action surface | Admin | Editor | Subscriber | Logged out | Missing nonce | Invalid nonce |
|---|---|---|---|---|---|---|
| REST settings save | Allowed | Blocked | Blocked | Blocked | Blocked | Blocked |
| REST cache purge/preload | Allowed | Blocked | Blocked | Blocked | Blocked | Blocked |
| REST database cleanup | Allowed with backup + irreversible confirmation | Blocked | Blocked | Blocked | Blocked | Blocked |
| Admin-post maintenance actions | Allowed | Blocked | Blocked | Blocked | Blocked | Blocked |
| Object-cache/drop-in actions | Allowed only with plugin settings/ownership checks | Blocked | Blocked | Blocked | Blocked | Blocked |

## Expected security controls

- Privileged actions use `current_user_can( 'manage_options' )` or the existing equivalent capability.
- Browser-triggered state changes use WordPress nonces.
- REST routes expose a `permission_callback`.
- Destructive actions require explicit confirmation beyond nonce/capability.
- Inputs are validated and sanitized before use.
- Output is escaped at rendering time.
- SQL uses WordPress APIs or prepared statements.
- File paths stay inside WordPress-owned paths.
- Logs do not store secrets, raw API tokens, payment data or unnecessary personal data.

## Runtime proof required

This document is not a substitute for execution. Mark the security score as fully proven only after the matrix above has been tested in a staging site.

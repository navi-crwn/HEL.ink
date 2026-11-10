# Permanent superadmin accounts

An account with the global `owner` role, or the configured `auth.superadmin_email`, is a permanent superadmin. Administrative actions against it are refused even when the actor is another owner or the account itself.

- The signed HOP admin API refuses all user actions: suspension, activation, deletion, role changes, session/token revocation and notes.
- Legacy account administration and self-service profile/avatar, password and API-token mutations are read-only. Two-factor is the exception (2026-11-10): the owner may turn it on, confirm it and renew recovery codes for itself, never turn it off, and a confirmed setup is never replaced (`enable` is refused). `php artisan hop:reset-2fa {email}` is the way back when the authenticator is lost.
- Valid password-reset requests cannot overwrite a superadmin password; the deletion service refuses before deleting files or contacting sibling apps.
- Existing Google sign-in does not overwrite the protected profile/linkage.
- Login/logout, authentication checks, session bookkeeping, audit events and read/export operations still operate normally.

Imperium also removes mutation controls and checks the authoritative target profile before forwarding an action. HOP ID enforces protection independently of that UI. Provision owner credentials and security settings through the existing owner-operated installation process before using the permanent account. The HTTP restriction does not prevent an operator with database or application-code access from changing it directly.

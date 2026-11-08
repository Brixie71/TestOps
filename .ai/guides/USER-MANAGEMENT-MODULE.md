# TestOps User Management Module
## Consolidated Technical Specification

**Project:** TestOps\
**Phase:** 0 - Detailed Technical Design\
**Module:** User Management\
**Status:** Requirements Consolidation\
**Framework:** Laravel 13\
**PHP:** 8.5\
**Database:** PostgreSQL target, SQLite-compatible testing\
**Frontend:** Blade, Tailwind CSS 4, Vite\
**Testing:** PHPUnit 12\

---

# 1. Purpose and Scope

The User Management module provides centralized administration of user accounts, system roles, account lifecycle operations, password management, email verification, notification preferences, audit history, and email delivery monitoring.

The module is primarily managed through Settings.

Authorized administrators:

- Super Admin
- SYSTEM ADMIN, subject to its defined restrictions

Ordinary users may manage their own profile, email address, password, and notification preferences within the approved security rules.

Project membership management remains outside this module and belongs to Project Administration.

# 2. Approved User Management Capabilities

The module must support:

1. User directory and listing.
2. Search, filtering, sorting, and pagination.
3. User account creation.
4. User profile editing.
5. Account activation and deactivation.
6. System role assignment.
7. Password setting and resetting.
8. Account restoration.
9. Permanent account deletion.
10. Email address changes and verification.
11. Audit history.
12. Bulk role assignment and deactivation.
13. Notification preferences.
14. Email delivery history and retry management.

# 3. User Profile and Account Structure

## 3.1 Profile Fields

The user profile must support:

- Name
- Email address
- Contact number
- Job title
- Department

Existing authentication fields remain:

- Password
- Email verification timestamp
- Remember token
- Account creation timestamp
- Account update timestamp

The existing `users` table must be inspected before proposing additional columns.

## 3.2 Account Status

Use Laravel Soft Deletes for account deactivation.

The intended statuses are:

| Status | Meaning |
|---|---|
| Active | Account is not soft-deleted |
| Deactivated | Account has a `deleted_at` timestamp |
| Permanently deleted | Account record has been removed |

Do not introduce a separate status column unless a demonstrated technical requirement justifies it.

## 3.3 Account Creation

- Super Admin and eligible SYSTEM ADMIN users may create accounts.
- Initial passwords are manually set by the administrator.
- Passwords must never be emailed.
- The account-created notification must be sent without including the initial password.
- The administrator communicates the initial password through a separate secure channel.
- Newly created accounts receive the default notification preferences.

# 4. User Directory

## 4.1 Display Columns

The user listing must display:

- Name
- Email
- System role
- Account status
- Department
- Creation date

SYSTEM ADMIN must be displayed as a distinct system role.

## 4.2 Search

Search must support:

- Name
- Email
- Contact number
- Job title
- Department

## 4.3 Filters

Support filtering by:

- System role
- Account status
- Department

## 4.4 Sorting

Support sorting by:

- Name
- Email
- System role
- Department
- Creation date

Use an explicit server-side sorting allowlist.

## 4.5 Pagination

- Default page size: 50 users.
- Search and filter conditions must persist when navigating pages.
- Search, filtering, sorting, and pagination must be performed server-side.
- Deactivated accounts must appear when the appropriate status filter is selected.

# 5. User Interface Structure

The Settings navigation should include:

```text
Settings
├── General
├── User Management
│   ├── Users
│   ├── Audit Logs
│   └── Email Delivery History
├── User Control
│   ├── Roles
│   └── Permissions
└── About
```

## 5.1 User Pages

The module must provide:

- User listing page
- User creation page
- Dedicated user details page
- Dedicated user editing page
- User audit history
- Account restoration action
- Account deactivation action
- Password management actions

The listing must use a table layout.

## 5.2 User Details

The details page should display:

- Profile information
- Account status
- System role
- Account creation date
- Relevant notification status
- User audit history
- Administrative actions available to the current actor

# 6. Role and Authorization Rules

## 6.1 System Roles

System roles must be loaded from the existing `roles` table using:

```text
scope = system
```

Project-scoped roles must never be assignable as system roles.

The existing `Role` and `Permission` models and tables should be reused.

Do not introduce additional role or permission tables without separate approval.

## 6.2 Super Admin

Super Admin retains exclusive authority over:

- Permanent account deletion.
- Creating and promoting Super Admin accounts.
- Modifying the built-in SUPER_ADMIN role and its permissions.
- All other approved administrative operations.

## 6.3 SYSTEM ADMIN

SYSTEM ADMIN has administrative capabilities equivalent to Super Admin, subject to these restrictions:

- Cannot permanently delete accounts.
- Cannot create Super Admin accounts.
- Cannot promote users to Super Admin.
- Cannot modify Super Admin accounts.
- Cannot deactivate another SYSTEM ADMIN.
- Cannot change another SYSTEM ADMIN's system role.
- Cannot modify the built-in SUPER_ADMIN role.
- Cannot modify permissions belonging to the built-in SUPER_ADMIN role.

SYSTEM ADMIN may:

- Create ordinary accounts.
- Edit eligible administrator and user profiles.
- Assign eligible existing system roles.
- Deactivate eligible accounts.
- Restore eligible accounts.
- Manage roles and permissions within its restrictions.
- View and export audit logs.
- View email delivery history.
- Retry failed email deliveries when authorized.

## 6.4 Last Active Super Admin Protection

The system must always maintain at least one active Super Admin.

This safeguard applies to:

- Deactivation
- Role changes
- Permanent deletion

SYSTEM ADMIN does not count as a Super Admin for this safeguard.

## 6.5 Self-Management Restrictions

Users may edit their own profile.

However, they cannot:

- Change their own system role.
- Change their own account status through profile editing.
- Assign themselves administrative permissions.

Self-email changes and self-password changes follow their separate security workflows.

# 7. Account Lifecycle

## 7.1 Deactivation

Deactivation must:

- Use Soft Deletes.
- Require an administrative reason.
- Invalidate all existing sessions for the target user.
- Cancel any pending email-change request.
- Invalidate associated verification tokens.
- Release the pending email address.
- Preserve the current verified email.
- Record the action in the audit log.

SYSTEM ADMIN must follow its restrictions when deactivating administrator accounts.

## 7.2 Restoration

Restoration must:

- Be restricted to authorized administrators.
- Restore the previous profile.
- Restore the previous system role.
- Require appropriate authorization.
- Record the restoration in the audit log.

If the account had a pending email change before deactivation, that request remains cancelled. The user must submit a new request.

## 7.3 Permanent Deletion

Permanent deletion is restricted to Super Admin.

Requirements:

- Account must previously have been deactivated.
- Require the acting Super Admin's current password.
- Require a deletion reason.
- Display dependent record categories that block deletion.
- Prevent deletion while blocking dependencies remain.
- Preserve independent audit history.
- Preserve historical testing identity snapshots.
- Invalidate all remaining sessions and authentication tokens.
- Release all email addresses associated with the account for future reuse.

Do not automatically cascade-delete historical testing records.

Project ownership must be resolved before deletion because the existing `projects.owner_id` foreign key uses restrictive deletion behavior.

# 8. Historical Identity Preservation

Permanent account deletion must not destroy historical attribution.

Audit logs and historical testing records must preserve:

- Original numeric user ID
- Name at the time of the event
- Email at the time of the event

The optional foreign-key reference to `users` may become `NULL` after permanent deletion.

Snapshot values must remain immutable.

This requirement applies to both:

- The actor who performed an action.
- The target user affected by an action.

Historical testing records must retain the identity of the person associated with the original testing activity.

The relevant testing modules do not yet exist, so their snapshot implementation must be designed when those modules are developed.

# 9. Email Address Management

## 9.1 Normalization

Email addresses must:

1. Have leading and trailing whitespace removed.
2. Be normalized to lowercase for comparison and storage.
3. Be compared case-insensitively.
4. Be checked against all currently reserved addresses.

For example:

```text
  User@Example.com
```

becomes:

```text
user@example.com
```

## 9.2 Email Uniqueness

Maintain a dedicated email-address history and reservation record for each account.

The record must track:

- Current verified email address.
- Pending email address.
- Previously verified email addresses.

Email uniqueness applies across:

- Active accounts
- Deactivated accounts
- Pending email-change requests
- Previously verified addresses associated with existing accounts

## 9.3 Email Reuse After Permanent Deletion

After permanent deletion:

- The current email address becomes available.
- Previously used email addresses become available.
- Historical identity snapshots remain unchanged.

This is an explicit exception to indefinite email reservation.

## 9.4 Self-Service Email Changes

Every user may change their own email address.

Requirements:

- Current-password confirmation.
- Email verification.
- Old verified email remains active until the new address is verified.
- The new pending address is reserved for that account.
- Previously verified addresses remain reserved while the account exists.

## 9.5 Replacing a Pending Email Change

If the user submits another email-change request while an earlier request is pending:

- Replace the previous pending request.
- Release the previous pending address.
- Reserve the new pending address.
- Invalidate the previous verification token.
- Issue a new verification token.

The current verified email remains unchanged until successful verification.

## 9.6 Pending Email Cancellation

Users may cancel their own pending email-change requests.

Requirements:

- Current-password confirmation.
- Invalidate all outstanding verification tokens.
- Release the pending email address.
- Preserve the current verified email.
- Record the cancellation in the audit log.

Authorized administrators may also cancel another user's pending request.

Administrative cancellation requires:

- Administrative authorization.
- A recorded reason.
- Audit logging.
- User notification sent to the current verified email address.

SYSTEM ADMIN must follow its existing restrictions when managing administrator accounts.

# 10. Email Verification Security

## 10.1 Expiration Configuration

The default verification-link expiration is:

```text
5 minutes
```

Super Admin may configure:

- A global default expiration.
- A per-user expiration override.

The effective expiration is:

1. Per-user override, when configured.
2. Global default otherwise.

Users cannot modify their own expiration settings.

Configuration changes apply to newly issued links only.

Existing links retain the expiration calculated when they were issued.

## 10.2 Single-Use Tokens

Verification tokens must:

- Be single-use.
- Be consumed after the first successful verification.
- Be rejected after successful consumption.
- Be rejected after expiration.
- Be invalidated when a replacement token is issued.

## 10.3 Resending Verification Emails

Resending must:

- Immediately invalidate the previous token.
- Issue a replacement token.
- Reset the expiration timer.
- Reset the token-level failed-attempt counter.
- Preserve the request-level failed-attempt counter.

A 60-second resend cooldown applies.

## 10.4 Failed Attempts Per Token

Each token allows a maximum of:

```text
5 failed attempts
```

After the fifth failed attempt:

- Invalidate the token.
- Require a new verification email.
- Preserve the pending email reservation.

## 10.5 Failed Attempts Per Request

A separate request-level counter tracks failed attempts across replacement tokens.

Approved settings:

| Setting | Value |
|---|---:|
| Maximum failed attempts | 10 |
| Lockout duration | 30 minutes |
| Counter reset on token replacement | No |
| Counter reset after successful verification | Yes |

During lockout:

- No new verification token may be issued.
- The pending email remains reserved.
- The user must wait for the lockout to expire.

## 10.6 Account-Level Rate Limiting

Maximum:

```text
5 verification emails per account per rolling hour
```

## 10.7 IP-Level Rate Limiting

Default:

```text
10 verification emails per IP address per rolling hour
```

Super Admin may configure the IP-based limit.

Both account and IP limits must pass before issuing a replacement token.

Rate-limited requests must not invalidate an existing valid token or release the pending email reservation.

## 10.8 Rolling-Window Enforcement

Rate limits use rolling windows.

Each request stops counting exactly one hour after its original timestamp.

Fixed clock-hour resets are not permitted.

# 11. Password Management

## 11.1 Password Policy

All newly set passwords must satisfy:

- Minimum 12 characters.
- At least one uppercase letter.
- At least one lowercase letter.
- At least one number.
- At least one symbol.

Passwords must never be logged, emailed, displayed, or stored in plaintext.

## 11.2 Administrative Password Management

Super Admin may choose between:

1. Manually setting a new password.
2. Forcing the user to complete a password reset through an email link.

SYSTEM ADMIN may perform approved password-management operations subject to its authorization restrictions.

Administrative password resets must:

- Require the acting administrator to confirm their own current password.
- Apply this requirement to both Super Admin and SYSTEM ADMIN.
- Invalidate the target user's existing sessions.
- Record the action in the audit log.
- Never expose the new password in notification emails.

## 11.3 Forced Password Reset

A forced reset must use:

- A secure, time-limited email link.
- Single-use reset credentials.
- A dedicated password-reset workflow.

Until completion, the affected user may access only the password-reset flow.

The exact token expiration, resend, and rate-limit configuration for password resets remains subject to separate design confirmation.

# 12. Audit Logging

## 12.1 Storage

Use a dedicated database audit table.

Audit history must be independent of user account records.

## 12.2 Retention

Audit logs are retained indefinitely.

## 12.3 Required Audit Information

At minimum:

- Actor user ID snapshot
- Actor name snapshot
- Actor email snapshot
- Target user ID snapshot
- Target name snapshot
- Target email snapshot
- Action type
- Timestamp
- Relevant before-and-after values
- Administrative reason, where required

Never record:

- Plaintext passwords
- Password hashes
- Verification tokens
- Raw password-reset links
- Authentication secrets

## 12.4 Audit Access

Super Admin and SYSTEM ADMIN have full audit access.

The interface must include:

- Dedicated Audit Logs page.
- Per-user audit history.
- Filtering.
- CSV export.
- PDF export.

Every audit-log viewing, searching, and exporting action must itself generate an audit event.

SYSTEM ADMIN has full access subject to its restrictions concerning Super Admin account and role management.

## 12.5 Audit Filters

Support filtering by:

- Actor
- Target user
- Action type
- Date range

Exports must use the same active filters as the Audit Logs page.

# 13. Bulk Operations

Support:

- Bulk system role assignment.
- Bulk account deactivation.

## 13.1 Processing

- No fixed user-selection limit.
- Process records in manageable chunks.
- Use partial processing.
- Return individual success and failure results.

## 13.2 Role Assignment

- Only existing system-scoped roles are eligible.
- Bulk Super Admin assignment is prohibited.
- Each target must pass authorization checks.

## 13.3 Deactivation

Protected accounts must be skipped with individual failure reasons.

SYSTEM ADMIN cannot deactivate another SYSTEM ADMIN.

The last active Super Admin safeguard must be enforced.

# 14. Email Notifications

## 14.1 Delivery Policy

Email notifications are synchronous.

Email delivery failures must not roll back successful account operations.

Failures must be recorded for manual follow-up.

## 14.2 Mandatory Security Notifications

These notifications cannot be disabled by users:

- Password resets
- Email verification
- Account deactivation and restoration
- System role changes
- Other security-related account changes

## 14.3 Notification Categories

| Category | Default | User configurable |
|---|---|---|
| Security and Account Activity | Enabled | No |
| Project Activity | Enabled | Yes |
| System Announcements | Enabled | Yes |

Users may independently disable Project Activity and System Announcements.

New accounts have both optional categories enabled by default.

## 14.4 Preference Enforcement

Check current notification preferences immediately before delivery.

This applies to notifications already scheduled or queued.

Disabled notifications must not be delivered.

Mandatory security notifications must still be delivered.

Suppressed notifications must be distinguishable from delivery failures in the notification history.

## 14.5 Administrative Email Cancellation Notification

When an administrator cancels a pending email-change request:

- Notify the user at their current verified email address.
- Explain that the pending request was cancelled.
- Confirm that their current verified email remains unchanged.
- Do not expose internal administrative details.

Notification failure does not roll back cancellation.

# 15. Email Delivery History

## 15.1 Administrative Page

Provide a dedicated Email Delivery History page under Settings.

Access is restricted to:

- Super Admin
- SYSTEM ADMIN

## 15.2 Display Fields

Show:

- Recipient email address
- Notification type
- Delivery status
- Timestamp
- Failure reason
- Retry status
- Retry attempt history

Never store or expose passwords, verification tokens, or raw reset links.

## 15.3 Retention

Retain email delivery history indefinitely.

## 15.4 Manual Retry

Authorized administrators may retry failed deliveries.

Requirements:

- Permission check.
- Audit entry for every retry.
- Preserve original delivery records.
- Record retry timestamp and outcome.
- Record failure reason when unsuccessful.

## 15.5 Retry Policy

| Setting | Value |
|---|---:|
| Maximum manual retries | 3 |
| Cooldown between retries | 5 minutes |
| After retry exhaustion | Manual intervention required |

The original failed delivery record must remain intact.

## 15.6 User-Facing Notification Status

Ordinary users may view relevant notification status on their own profile.

They may see:

- Notification type
- Date and time
- Basic delivery status
- Simple failure explanation

They must not see internal mail-server errors.

Users cannot manually retry failed notifications from their profile.

They may request new verification or password-reset emails through their normal workflows.

# 16. Historical and Deletion Dependencies

Permanent deletion must show blocking dependency categories.

Examples include:

- Projects owned by the user.
- Historical testing records that still require an account reference.
- Other records with restrictive foreign-key dependencies.

Audit logs must not block deletion because they are independently preserved.

Historical testing records must preserve immutable user identity snapshots.

Do not change existing foreign-key deletion behavior without a reviewed schema proposal.

The current `project_user.role_id` cascade behavior must be reviewed before implementing role lifecycle operations.

# 17. Database Design Constraints

Existing structures should be reused where possible.

Existing tables include:

- `users`
- `roles`
- `permissions`
- `permission_role`
- `projects`
- `project_user`
- `sessions`
- `password_reset_tokens`

Potential additional structures requiring schema design include:

- Email history and pending email reservation.
- Audit logs.
- Email delivery history.
- Notification preferences.
- Verification-expiration configuration.
- Per-user verification-expiration overrides.
- Email-change verification request and token tracking.

These are proposed schema responsibilities, not approved table names or migration definitions.

The final schema must be designed against the actual applied migrations.

Do not modify applied migrations.

# 18. Technical and Security Constraints

- Backend authorization is mandatory.
- Hiding navigation items is not a security mechanism.
- Use explicit authorization policies or permission checks.
- Use server-side validation.
- Use database transactions for operations requiring atomic state changes.
- Do not allow mass assignment of sensitive account fields.
- Do not expose passwords or authentication secrets.
- Invalidate sessions after deactivation, administrative password reset, and permanent deletion.
- Do not cascade-delete historical testing data.
- Use normalized email addresses for uniqueness.
- Protect built-in Super Admin role and permissions.
- Avoid adding unapproved domain models.
- API authentication is Passport OAuth (machine and human OAuth tokens; see docs/oauth-service-authorization.md). Do not assume Sanctum.

# 19. Implementation and Verification Workflow

Before implementation:

1. Read `.ai/rules/index.md`.
2. Read applicable rule files.
3. Read relevant database and Eloquent guides.
4. Inspect current models, migrations, routes, controllers, and tests.
5. Run `php artisan migrate:status`.
6. Review actual database structure.
7. Prepare a schema specification.
8. Generate one logical migration at a time.
9. Review migration `up()` and `down()` methods.
10. Obtain explicit approval before applying migrations.

After implementation:

- Run Laravel Pint against modified PHP files.
- Run focused PHPUnit tests.
- Run the full test suite before completion.
- Verify database migration status.
- Record unresolved decisions.

Never use `migrate:fresh`, `migrate:refresh`, or destructive reset commands as routine troubleshooting.

Development must remain incremental. Do not generate the entire module in one pass.

# 20. Unresolved Design Decisions

The following items must be settled before their corresponding implementation begins.

| Area | Unresolved item |
|---|---|
| Password reset | Exact forced-reset token expiration, resend limits, and failed-attempt behavior |
| Email delivery | Exact representation of suppressed notifications and delivery attempts |
| Notification preferences | Whether additional notification categories will be needed as project features expand |
| Email configuration | Actual SMTP/provider configuration and production delivery verification |
| Deletion dependencies | Final list of restrictive dependencies after the remaining domain modules exist |
| Historical records | Snapshot fields and foreign-key behavior for each future testing model |

Do not silently resolve these items during implementation.

# 21. Completion Criteria

The User Management module is complete only when:

- All approved requirements are implemented.
- Super Admin and SYSTEM ADMIN restrictions are enforced server-side.
- Account lifecycle operations work correctly.
- Email verification and security controls pass tests.
- Password management follows the approved policy.
- Historical identity is preserved.
- Audit records are independent of deleted accounts.
- Notification preferences are enforced at delivery time.
- Email delivery history and retries work as specified.
- Bulk operations report individual results.
- Database migrations are reviewed and applied safely.
- Modified files pass Pint.
- Focused and full test suites pass.
- Deferred decisions are documented.

**Current status:** Requirements consolidation completed. Database schema design and implementation remain pending.

**Stage 3B implementation status (user deactivation / reactivation):**
- Deactivation (`App\Actions\DeactivateUserAction`) uses SoftDeletes only — no new `account_status` value; requires a non-empty reason (max 500 chars); protects the last active Super Admin; revokes user-bound OAuth credentials via `App\Services\Auth\RevokesUserCredentials`; purges only the target user's database sessions on the configured session connection/table; audits `user.deactivated` and `oauth.credentials.revoked` (aggregate counts only, no credential IDs or secrets) inside the lifecycle transaction. The transaction first locks the canonical SUPER_ADMIN system role row (`code = SUPER_ADMIN`, `scope = system`) with `lockForUpdate()` and reloads the target (including soft-deleted rows) and the actor from the database under the lock, so stale pre-transaction models can never drive the checks — the serialization root for the last-active-Super-Admin safeguard (fails closed if the row is missing; SQLite compiles the lock as a no-op, real concurrency is only enforced on PostgreSQL/MySQL).
- Reactivation (`App\Actions\ReactivateUserAction`) restores soft-deleted accounts, preserves their existing `account_status` (invited accounts are not activated), never restores revoked credentials or old sessions, and audits `user.reactivated` inside the transaction. It serializes on the same canonical SUPER_ADMIN role row as deactivation (reactivation changes the active Super Admin count, so it acquires the same `lockForUpdate()` and fails closed when the row is missing) and reloads the target (including soft-deleted rows) and the actor under the lock before the authorization and eligibility checks; no last-active-Super-Admin restriction applies because reactivation can only increase the count.
- Authorization (server-side, `App\Services\Users\UserLifecycleAuthorizer` on `PermissionChecker`): `users.deactivate` is seeded to SYSTEM_ADMIN via `UserManagementPermissionSeeder`; SUPER_ADMIN unrestricted; project-scoped roles never grant lifecycle authority; SYSTEM_ADMIN cannot manage SUPER_ADMIN or other SYSTEM_ADMIN accounts; self-deactivation/reactivation denied; last active SUPER_ADMIN protected.
- Database connection consistency: `App\Services\Users\LifecycleConnectionGuard` fails closed before any lifecycle transaction unless lifecycle, session (null→default), and Passport (null→default) connections resolve to one connection, keeping all changes transactionally atomic.
- `EnsureActiveAccount` middleware (alias `active.account`) logs out soft-deleted or non-`active` accounts on authenticated web routes (after `auth`; never on login/logout/invitation paths), invalidating the session and regenerating the CSRF token.
- Deferred by approved decision: pending email-change cancellation (no email-change module exists; integrate when it is implemented), deactivation notification delivery (no user-notification workflow yet; never send email/network work inside lifecycle transactions), permanent deletion (out of scope; `projects.owner_id` restriction remains; no automatic ownership transfer). Email-address history and reservation records are preserved during deactivation.

**No code or database changes are authorized by this document alone.**

# TestOps — Account Creation & Invitations Module

## Phase 1: Architecture and Technical Design

**Project:** TestOps
**Module:** Account Creation & Invitations
**Status:** Phase 1 (Credential core) is **implemented and merged into the working tree**; see **Appendix A** at the end of this document for the implemented names, implemented deltas from this design, deferred scope, and open product decisions. Phases 2–7 remain unimplemented.
**Date:** 2026-11-09
**Stack baseline:** Laravel 13, PHP 8.5, PostgreSQL target / SQLite tests, Blade + Tailwind CSS 4 + Vite, PHPUnit 12, Passport OAuth, Haraka 3 + Mailpit local mail, Podman Compose.

Authoritative requirements: the approved functional specification in this task (sections **A–L**), plus the existing approved guide `.ai/guides/USER-MANAGEMENT-MODULE.md` where not superseded. Conflicts between the two are documented in §21, never silently resolved.

---

# 1. Executive Summary

TestOps today can deactivate/reactivate accounts, view a user directory, and record email delivery attempts — but **it cannot create accounts at all**. There is no account-creation flow, no invitation issuance, no email verification at runtime, no Mailables, no scheduler, no notification preferences, and the `user_invitations` table is an unused schema scaffold.

This design reuses everything that genuinely exists (RBAC, `PermissionChecker`, `AuditLogger`, `NotificationDeliveryRecorder`/`NotificationDeliveryRetryService`, `user_email_addresses` ledger, `system_settings` typed-setting pattern, User Directory conventions, the `lockForUpdate()` transaction-serialization pattern) and adds a thin but complete credential-and-approval layer:

- **One credential model** for both creation methods: `user_invitations` is extended with a `method` discriminator (`invite` | `direct`). Direct creation is "an invitation that sets a password without an acceptance-flow step." Both methods share validation, authorization, audit, and activation.
- **Accounts are created (rows in `users`, status `invited`) at credential-issue time** in both methods. This reserves the email immediately via the existing `users.email` UNIQUE constraint, enables role/membership assignment at creation, and gives the User Directory natural rows to display.
- **One shared single-use email-verification mechanism** (`email_verification_requests`) is built once and is the module's confirmed dependency for activation; a future email-change workflow will reuse it without new infrastructure (email change itself remains out of scope, as deferred in the approved guide).
- **Full template system** (`content_templates` + immutable `content_template_versions`, restore-creates-new-version) for the acceptance page and invitation/direct-creation emails, with code-enforced protected sections and a placeholder whitelist.
- **Profile-change approval workflow** normalized into request → per-field child rows → append-only activity history, with reviewer assignment (auto strategies + manual), delegation, workload caps, reminders, and escalations — all concurrency-safe via `lockForUpdate()` on the request row.
- **Notification layer**: in-app feed (`user_notifications`), per-user channel preferences, per-event configs and recipient rules, layered on top of the existing delivery recorder/retry bookkeeping.
- **A scheduler** (Laravel `routes/console.php` Schedule) for link-expiry events, reviewer reminders, and overdue escalations — none exists today; this is an explicit new prerequisite.

No package dependencies are added. All migrations are append-only and PostgreSQL/SQLite portable. Any behavior change to existing tables is additive (new columns on `user_invitations` only).

---

# 2. Existing Architecture Assessment (source of truth = code)

## 2.1 What exists and works

| Area | Status | Key facts |
| --- | --- | --- |
| Users table | Exists | `users.email` UNIQUE (survives soft-delete; reservation is indefinite while the account row exists, released only on permanent deletion — which is not implemented); `account_status` values in use: `'invited'` (DB default), `'active'` (login gate), `'deactivated'` (display-only filter mapped to SoftDeletes trash); `contact_number string(30)`, `job_title`/`department string(100)` nullable; `email_verified_at` column **exists but nothing writes it at runtime**; `MustVerifyEmail` is **commented out** in `App\Models\User`. |
| RBAC | Exists | `roles` (`code` unique, `scope` system/project), `permissions` (`code` unique), `permission_role` pivot (unique pair). `PermissionChecker` (Super Admin ⇒ true; system roles via role-permission relations); `permission:` middleware alias; Policies as gates. `SUPER_ADMIN`/`SYSTEM_ADMIN` system roles seeded by `RoleSeeder`; project roles by `ProjectRoleSeeder`. |
| User Directory / Viewing | Exists | `Settings\UserDirectoryController` with SoftDeletes-aware status filter (`in:invited,active,deactivated`), sort allowlist; `UserLifecycleController` deactivation/reactivation with `LifecycleConnectionGuard` and `lockForUpdate()` on the canonical SUPER_ADMIN role row as serialization root. **No account-creation flow exists anywhere.** |
| Email ledger | Exists | `user_email_addresses` (2026_11_04): one row per address, `email` UNIQUE, `status='current'`; only ever populated with the signup email today. |
| Invitations scaffold | Exists, unused | `user_invitations` (2026_11_05): `user_id` nullable FK, `invited_email` nullable, `token_hash` UNIQUE, `expires_at`, `accepted_at`, `revoked_at`, `created_by`, timestamps. **No rows are ever written; no code path reads it.** `User::invitations()` / `createdInvitations()` relations exist. |
| Password reset | Exists (Laravel default) | `password_reset_tokens` + Breeze-style reset controllers — separate concern, not used for invitations; kept untouched. |
| Delivery bookkeeping | Exists | `NotificationDeliveryRecorder` wraps record creation; `NotificationDeliveryRetryService` enforces `MAX_MANUAL_RETRIES = 3` and a 5-minute cooldown — **exactly matching spec E** — over unique `(delivery_id, attempt_number)` retries. Failures carry `TransportExceptionInterface`-derived codes (`mail_transport_failed`, `delivery_failed`). `NotificationDelivery` is immutable (LogicException on update/delete). **There is no actual send layer, no Mailables, no preferences, no queue usage.** |
| Audit | Exists | `App\Services\Audit\AuditLogger` — caller-transaction-owned (audit rows commit or roll back **with** the caller's transaction), `SENSITIVE_KEYS` sanitize list, string action catalog (`user.deactivated`, `user.reactivated`, `oauth.credentials.revoked`, `notification.retry`, …). |
| Settings | Exists (API-only) | `system_settings` key/value; typed per-domain setting services with DEFAULT/MIN/MAX (pattern: `EmailLinkExpirationSetting`, key `email_link_expiration_minutes`, default 60, persisted by `SystemSettingSeeder`). API surface only (`GET/PUT api/v1/system-settings/...`, `settings.*` view permissions, writes Super Admin only, machine clients hard-denied). **Web settings pages are Super-Admin-gated `Route::view` placeholders** (`settings.index/roles/permissions/about`). |
| Mail infra | Exists (local) | Haraka (auth + TLS) → Mailpit UI; `infrastructure/email/compose.yaml` under Podman; SMTP `127.0.0.1:2525`; `MAIL_MAILER=log` is the `.env.example` default; nothing in the app sends mail yet. |
| Middleware | Exists | `EnsureActiveAccount` logs out non-`active`/trashed users; **invitation/acceptance routes will be exempted** like the existing invitation-path exemptions. Login requires `account_status = 'active'` in credentials. |
| Scheduler / queues | **Absent** | `QUEUE_CONNECTION=database` configured but nothing dispatches; no console scheduler entries (`routes/console.php` empty of schedules); no custom Artisan commands. |
| Email verification | **Absent at runtime** | Column exists, interface commented out, no verification request model, no routes. The approved spec's "reuse the existing system" cannot be satisfied literally — see §21 R1. |
| Email change | **Deferred** | Explicitly deferred in `USER-MANAGEMENT-MODULE.md` (line 935). Must not be built here. |
| Notification preferences | **Absent** | No per-user preference storage exists. |

## 2.2 Gaps that block the spec

1. Account creation and invitation issuance (nothing exists).
2. Email verification requests/tokens (must be **built**, then *reused*).
3. Actual mail sending (Mailables) behind the existing delivery bookkeeping.
4. In-app notifications, preferences, event configs, recipient rules.
5. Templates with version history.
6. Profile-change request workflow and reviewer assignment (entirely absent).
7. Rate limiting specific to invitation credentials (login throttle exists but is unrelated).
8. Scheduler for expirations/reminders/escalations.

---

# 3. Reusable Existing Components (do not duplicate)

| Existing component | Reuse in this module |
| --- | --- |
| `PermissionChecker` + `permission:` middleware + Policies | All new authorization; no parallel check system. New permissions seeded to roles by a new seeder following `UserManagementPermissionSeeder`. |
| `AuditLogger` | Every state transition recorded **inside the caller's DB transaction** so audit commits/rolls back atomically with the change. New action names in §15. Sensitive keys sanitized by the existing `SENSITIVE_KEYS` list (extended only if a new key name slips through — see §16). |
| `NotificationDeliveryRecorder` / `NotificationDeliveryRetryService` / `notification_deliveries` + retries | The persistence half of every email send; manual-retry limits/cooldown already spec-compliant. This module supplies the missing *send closure* and UI surfacing. |
| `user_email_addresses` ledger | Email reservation/audit trail: a `reserved` row at credential issue, flipped to `current` at activation. |
| `system_settings` + typed setting classes (`EmailLinkExpirationSetting` pattern) | All new configurable knobs as namespaced setting services with DEFAULT/MIN/MAX and change audit (§11.4). |
| `LifecycleConnectionGuard` + `lockForUpdate()`-on-canonical-role-row pattern | Transaction serialization idiom for acceptance, credential rotation, reviewer assignment (§16). |
| User Directory + User Viewing controllers/views | Extended — **no separate Invitation Management page** (spec L). Status filter gains invitation-derived states; User Viewing gains an Invitation Details panel. |
| `password_reset_tokens` + reset flow | Untouched; a separate concern. Password *setup* links are new credentials, distinct from resets. |
| Middleware exemption pattern for invitation routes | Public acceptance routes exempt from `EnsureActiveAccount` (guest + token), consistent with existing exemptions. |
| `EnsureActiveAccount` + login `account_status='active'` | The activation gate: `invited` accounts cannot log in; acceptance completes before activation, so no protected access pre-verification. |
| `routes/console.php` | Host of the new scheduler (Laravel-native, no extra deps). |

---

# 4. Functional Requirement Mapping (A–L → design)

| Req | Requirement | Design response |
| --- | --- | --- |
| A.1 | Direct account creation; secure password-setup link; **15-minute** expiry; user sets own password | `user_invitations.method='direct'`; password-setup credential = invitation token variant; 15-min expiry fixed per spec; acceptance flow skips review step (config `password_setup_only`). §6.1, §8.1, §13 |
| A.2 | Invitation-based creation; **15-minute** link expiry | `method='invite'`; same credential store. §6.1, §8.1 |
| A.3 | Shared validation/authorization/audit/activation for both methods | One `IssueAccountCredentialsService`, one acceptance pipeline, `method` is data not code forks. §10 |
| A.4 | Required fields configurable: Email Only / Email + Full Name | `AcceptanceFlowSetting` + `ProfileFieldRequirementSetting`; enforced at issue-time validation and acceptance; state snapshotted per invitation (C). §11.4 |
| A.5 | Contact number/job title/department initially optional; later configurable | Profile-field settings service; per-field rules in §11.4.3. |
| B.1 | One active link + one recovery code per invitation; registry uniqueness | Partial unique index: one un-accepted, un-revoked invitation per user; one unconsumed recovery code per invitation (data columns, no duplicates by update-in-transaction). §7 |
| B.2–B.3 | Recovery codes single-use, hashed only, 15-min expiry | `recovery_code_hash` (HMAC-SHA256), `recovery_code_expires_at`, single-use enforced server-side under row lock. §16.2 |
| B.4–B.5 | Recovery codes only request replacement links; never activate/grant access | Recovery endpoint issues a **new link + new credentials** only; acceptance always re-authenticates with the current link token. §16.2 |
| B.6 | Configurable format: numeric 6 / alphanumeric 9 | `InvitationSecuritySetting.recovery_code_format` (enum + length constants). §11.4 |
| B.7 | Successful resend/retry ⇒ new credentials; old link + old code invalidated | `RotateInvitationCredentialsService`: regenerate `token_hash`, null out recovery hash, inside one transaction. §8.1 |
| B.8 | Cancel/accept/replacement invalidate old credentials | Same rotation/cancel paths; generation counter `credential_generation` makes stale links verifiably dead. §6.1 |
| B.9 | Raw tokens/codes never stored readable | HMAC-SHA256 (keyed, deterministic ⇒ indexable, unreadable); raw values only in the outbound email body; never logged, audited, or echoed back. §16.1 |
| B.10 | Records + delivery history retained until account permanently deleted | No pruning jobs for invitations/deliveries; FK `cascadeOnDelete()` from `users` is the only deletion path (permanent deletion itself remains unimplemented — §21 R9). §6 |
| B.11 | Limits configurable by Super Admin and System Admin; defaults 60 s cooldown / 5 per invitation per hour / 10 per IP per hour / 5 failed attempts / 30-min lockout | `invitation_link_requests` rolling-window table + `InvitationSecuritySetting` defaults exactly as listed. §6.2, §11.4.1, §16.3 |
| C.1–C.2 | Acceptance flow settings: Password Setup Only / Review Details + Password Setup; **captured at invitation creation** | `acceptance_flow` column snapshotted on the invitation row; runtime setting changes cannot affect in-flight invitations. §6.1 |
| C.3 | No extra profile-completion step | Flow options are exactly the two named; nothing else is added. |
| C.4–C.6 | Open link → flow → password → **verify email** → activate; **reuse existing verification**; no protected access pre-verification | Single `email_verification_requests` mechanism (§6.3); verification + activation + acceptance commit **atomically**; `EnsureActiveAccount`/login gate keeps `invited` accounts out until then. Reuse is honest: the mechanism must first be built (§21 R1). §8.3 |
| D.1–D.5 | System role mode configurable; default = restricted default role, no choice at creation; Super Admin configures default role; SUPER_ADMIN/SYSTEM_ADMIN excluded from selection; future accounts only | `DefaultRoleSetting` (`users.default_role_code`, `users.role_assignment_mode`); role picker hidden/disabled when mode = default; seeder-level guard that the default role is `scope='system'` and not SUPER_ADMIN/SYSTEM_ADMIN codes. §11.4.2 |
| D.6–D.7 | Project membership/roles: default none at creation; optional assignment during creation/invitation | Optionally accepted member/role input on the create/invite forms when the actor holds `projects.manage-members` (existing permission — reuse, no new permission). |
| D.8–D.10 | Preserve RBAC boundaries; Super Admin exclusive: create/promote Super Admin, modify SUPER_ADMIN role/permissions, permanent deletion | Authorization matrix §9; seeder refuses attaching `users.promote-super-admin` to anything but SUPER_ADMIN; permanent deletion stays out of scope (unimplemented, §21 R9). |
| E.1–E.2 | Resend pending/expired; cancel pre-acceptance | Resend = rotate credentials to a fresh token; cancel = `revoked_at` + actor + reason on the invitation row; both directory-integrated. §8.1 |
| E.3–E.5 | Cancellation immediate invalidation; actor/timestamp/reason recorded; duplicates blocked; blocked for existing active/deactivated emails | Generation bump + FK integrity + partial unique index; creation validation consults `users` (any existing row that is `active` or soft-deleted ⇒ reject; `invited` rows may be re-invited). §7, §16.4 |
| E.6 | Cancelled cannot be revived | No "uncancel" path; a new invitation (new row, same user) is the only restart. §8.1 |
| E.7–E.10 | Delivery failure ⇒ stays pending, status Failed, manual retry from User Viewing, all attempts recorded, limit 3, cooldown 5 min, each retry new credentials | Latest `notification_deliveries` row per invitation = delivery status; retry via existing `NotificationDeliveryRetryService` + credential rotation in one action. §6.1, §13 |
| F.1–F.7 | Acceptance-page customization (branding/logo, org identity, title, welcome, instructions, supporting text, contact info) | `content_templates` kind `acceptance_page`, payload schema per field; public acceptance page renders from active version. §6.4 |
| F.8 | Protected system content invariant | Protected strings/steps are hardcoded UI sections rendered around the editable payload; template payloads cannot remove them (renderer composes fixed fences). §12.3 |
| F.9–F.12 | Email templates: Direct / Invitation / optional resend variants; subject/body/branding/placeholders; protected security info unremovable | Template keys `invitation.email.initial`, `invitation.email.direct`, `invitation.email.resend` (+ optional per-key resend variants); placeholder whitelist per key. §6.4, §13.2 |
| F.13–F.18 | Full version history, editor+timestamp, change records, inspection, restore-creates-new-version, all audited | `content_template_versions` append-only; restore copies payload into a **new** version; audit `template.version_created` / `template.version_restored`. §6.4, §8.4 |
| G.1–G.4 | Fields email/full name/contact/job/department; email always required; requiredness configurable by SA+SysAdmin; applies to all four surfaces | `ProfileFieldRequirementSetting` enforced in: credential-issue validation, acceptance review step, admin profile edit, self-service profile update. §11.4.3 |
| G.5–G.7 | Newly-required optional field: existing accounts stay usable; values not auto-modified; next profile update must supply; new accounts must comply | Requirements evaluated at the moment of each write; no backfill job exists. §11.4.3 |
| G.8–G.9 | Visibility independently configurable for User Profile / Directory / Viewing; never overrides authorization | `ProfileFieldVisibilitySetting` (three booleans per field) filters **display** only; every endpoint keeps policy/permission checks. §11.4.3 |
| H.1 | Users submit profile changes | `ProfileChangeService::submit()` builds request + field rows snapshotting `old_value` from `users` at submission. §6.5, §8.5 |
| H.2 | Approval configurable per field (name/contact/job/department) | `ProfileFieldApprovalSetting` boolean per field. §11.4.3 |
| H.3 | Email changes stay in the existing (deferred) email-change workflow | Out of scope here; `email_verification_requests` built so email-change can adopt it later (§21 R10). |
| H.4 | Only one pending request per user | Partial unique index on `profile_change_requests(user_id)` for the three "live" statuses. §7 |
| H.5–H.6 | Official values stay on `users`; proposed displayed separately; reviewer compares old vs proposed | Field rows carry `old_value` + `proposed_value`; `users` untouched until approval. §6.5 |
| H.7 | Approve / Return for Correction | Per-field decision actions. §10 |
| H.8 | Returned ⇒ edit + resubmit as the SAME request | `status='returned_for_correction'`; resubmission reuses the row, appends activity events. §8.5 |
| H.9 | Correction history preserved | Append-only `profile_change_request_activities`. §6.5 |
| H.10 | Per-field approve/return; Partially Approved overall | Request aggregator status `partially_approved` computed on write. §8.5 |
| H.11 | Unlimited correction cycles | No cycle counter/limit; every cycle appended. |
| H.12–H.13 | User can withdraw own pending; admins can cancel with reason | Withdraw (self) / cancel (permission, reason required). |
| H.14 | Self-approval prohibited | Server-side check in decision action (index lookup on user_id); UI hides affordance but does not carry authorization. §9 |
| H.15 | All submissions/corrections/assignments/decisions audited | §15 event catalog. |
| I.1–I.4 | Assignment mode auto/manual; strategies round-robin / lowest workload / default reviewer | `ReviewerAssignmentSetting` + `AssignReviewerService`. §10, §11.4.5 |
| I.5–I.7 | No eligible reviewer ⇒ stay pending, notify admin, wait for manual assignment | Assignment service returns `unassigned` outcome + admin notification event `profile_review.no_reviewer`. |
| I.8–I.9 | Eligibility by system role and individual user | Eligibility evaluator: role codes + explicit user includes/excludes (settings tables). §11.4.5 |
| I.10–I.11 | Delegation: reviewer requests, admin approves before reassignment | `delegation_requested_at`, `delegation_proposed_reviewer_id`, `delegation_status` on active assignment row; approval creates a **new** assignment row (history preserved). §6.5 |
| I.12–I.14 | Reviewer deactivation / eligibility removal: preserve history, block actions, notify admin, wait for manual reassignment | Actions validated against current eligibility (assignment history rows untouched); periodic + on-event check notify admins. §8.6 |
| I.15–I.17 | Workload cap configurable; auto-assignment respects it; manual can override and override is recorded | Cap under lock; manual assignment bypass allowed with `override_workload_limit=true` recorded on the assignment row + audit. |
| J.1–J.3 | Reviewer reminders: enable/interval/email+in-app independently | `ReminderEscalationSetting` + scheduler reminders (idempotent via last-reminded timestamp). §11.4.6, §14 |
| J.4–J.7 | Escalation configurable: notify-only / auto-reassign / notify+permit-manual; default manual | Scheduler escalation job reads mode; default `manual_reassignment` (notify only). |
| J.8–J.9 | Escalation recipients by role and individual; recipients don't automatically gain review permissions | Recipient rules configure **notifications only**; review authorization remains eligibility-based, decoupled. §9 note |
| J.10–J.13 | Request visibility configurable (role + individual); own requests always visible; assigned reviewers see assigned; admins per config; viewing ≠ approving; existing authorization/personal-data restrictions hold | Visibility filters in query scopes; decision/assignment actions still permission-gated independently of view grants. §9 |
| K.1–K.7 | Invitation event list (accepted, expired, delivery failed, cancelled, repeated failed recovery attempts, resent, account activated) — configurable events/channels/roles/individuals/creator; security-critical events + audit undeactivable | Event catalog §14.1; `notification_event_configs` + recipient rules; `security_critical=true` rows toggles rejected server-side; AuditLogger always fires regardless of notification settings. |
| L.1 | No separate Invitation Management page | All invitation UI lives in the existing User Directory / User Viewing. §12.2 |
| L.2 | Directory lists active / pending / expired / cancelled / deactivated via filter | Status filter extension mapping account + latest-invitation state (§12.2). |
| L.3 | Separate View and Edit; More Actions = deactivation; actions per authorization and state | Existing directory layout conventions extended with state-aware affordances (UI flags only). |
| L.4 | User Viewing hybrid layout + Invitation Details when applicable | New read-only panel on the existing User Viewing page. |
| L.5–L.6 | Viewing read-only; changes via Edit page | No new mutation endpoints on viewing; Edit flows are separate. |
| L.7 | Never expose passwords, raw tokens, recovery codes | §16.5 — hashing + `Hidden` casts + audit sanitization + no echo-backs. |

**Coverage check:** every lettered bullet A–L maps to at least one design element above.

---

# 5. Proposed Domain Model and Relationships

```
users (existing; email UNIQUE = reservation anchor; account_status invited→active)
  ├── user_invitations (1:N)            method, credentials, config snapshot, counters
  │     ├── invitation_link_requests    rolling-window rate-limit/attempt ledger
  │     └── notification_deliveries     per send attempt (existing, immutable)
  ├── email_verification_requests       ONE shared single-use verification mechanism
  ├── user_email_addresses              reserved → current lifecycle rows (existing)
  ├── profile_change_requests           at most one "live" request per user
  │     ├── profile_change_request_fields       current cycle per field (unique per request)
  │     ├── profile_change_request_activities   append-only correction/decision history
  │     └── profile_change_reviewer_assignments assignment history (latest = active)
  ├── user_notifications                in-app feed
  └── notification_preferences          per user × event × channel

content_templates ──< content_template_versions (append-only; active pointer on template)
notification_event_configs (event_key unique) ──< notification_recipient_rules
system_settings (existing) — all module configuration
```

Deliberate non-duplications:

- **No password/credential copy** exists anywhere except `users.password`; account activation writes it once.
- **No email duplicate-at-rest contrast**: `users.email` remains authoritative; the ledger mirrors lifecycle, not truth.
- **No invitation status enum column apart from timestamps**: pending/expired/cancelled/accepted are *derived* from `accepted_at`, `revoked_at`, `expires_at`, and the latest delivery row — one source of truth (a computed accessor), not a mutable status field that can drift.
- **Proposed profile values never written to `users`** until approval; `old_value` snapshots are datestamped at submission and re-validated at decision time (§8.5).

---

# 6. Proposed Database Tables and Columns

All migrations are **new, append-only** (never edit deployed migrations). Existing scaffold columns are kept as-is.

## 6.1 ALTER `user_invitations` — extend the existing scaffold

| Column (added) | Type | Notes |
| --- | --- | --- |
| `method` | string(20), NOT NULL, default `'invite'` | `'invite'` \| `'direct'`; CHECK constraint. |
| `credential_generation` | unsignedInteger, NOT NULL, default 1 | Bumped on every rotation; enables generation-scoped token validation and cheap stale-link checks. |
| `recovery_code_hash` | string(255), NULL | HMAC-SHA256 of the raw recovery code; NULL ⇒ no active recovery code. |
| `recovery_code_expires_at` | timestamp NULL | Now + 15 min (fixed per spec B). |
| `recovery_code_generated_at` | timestamp NULL | Audit convenience. |
| `recovery_failed_attempts` | unsignedInteger NOT NULL default 0 | Reset on success or new code. |
| `recovery_locked_until` | timestamp NULL | Lockout after 5 failures; 30 min default (configurable). |
| `acceptance_flow` | string(30), NOT NULL | **Snapshot at creation**: `'password_setup_only'` \| `'review_then_password'`. Later settings changes cannot alter this invitation. |
| `sent_count` | unsignedInteger NOT NULL default 0 | Total credential deliveries issued (reporting only; limits derive from §6.2). |
| `last_sent_at` | timestamp NULL | Cooldown anchor for the 60-second rule. |
| `cancelled_reason` | text NULL | Required when cancelled. |
| `cancelled_by` | FK `users.id` NULL | Cancel actor. `revoked_at` (existing) = cancellation timestamp. |
| `expired_notified_at` | timestamp NULL | Idempotency marker for the scheduler's expiry notifications. |

Keep existing `user_id`, `invited_email` (legacy compat; new rows always populate `user_id`), `token_hash` UNIQUE, `expires_at`, `accepted_at`, `revoked_at`, `created_by`.

## 6.2 CREATE `invitation_link_requests`

Rolling-window ledger backing **all** invitation-specific limits (spec B defaults: 60 s cooldown, 5/invitation/hour, 10/IP/hour):

| Column | Type |
| --- | --- |
| `id` | id |
| `invitation_id` | FK → `user_invitations.id`, cascadeOnDelete |
| `user_id` | FK → `users.id`, cascadeOnDelete (denormalized for per-account/IP queries) |
| `request_type` | string(30): `initial_send` \| `resend` \| `manual_retry` \| `recovery_replacement` |
| `ip_address` | string(45) NULL (IPv6-safe, plain for admin debugging; not a credential) |
| `outcome` | string(20): `issued` \| `rejected` (rejected rows recorded too, for forensics) |
| `created_at`, `updated_at` | timestamps |

Indexes: `(invitation_id, created_at)`, `(user_id, created_at)`, `(ip_address, created_at)`.

## 6.3 CREATE `email_verification_requests` — the one shared mechanism

Single generic request so the future email-change workflow reuses it verbatim (purpose discriminator, no schema change):

| Column | Type |
| --- | --- |
| `id` | id |
| `user_id` | FK → `users.id`, cascadeOnDelete |
| `purpose` | string(30): `'account_activation'` (this module) \| `'email_change'` (future) |
| `email` | string(255) — the address this request verifies (snapshot; activation = the account's email) |
| `token_hash` | string(255) UNIQUE — HMAC-SHA256, raw token only ever emailed |
| `expires_at` | timestamp |
| `consumed_at` | timestamp NULL — single-use marker |
| `created_at`, `updated_at` | timestamps |

Indexes: partial UNIQUE `(user_id, purpose)` WHERE `consumed_at IS NULL`; `(user_id, created_at)`.
Semantics: resolve by `token_hash` + row lock + `expires_at` > now + `consumed_at IS NULL`, then set `consumed_at` in the same transaction that acts on the outcome. For `account_activation` the consuming action writes `users.email_verified_at`.

## 6.4 CREATE `content_templates` + `content_template_versions`

```
content_templates
  id, template_key string(100) UNIQUE
  kind string(20)  'acceptance_page' | 'email'
  title string(150)
  active_version_id  FK → content_template_versions.id NULL (nullable circular FK, added in a second migration step)
  timestamps
```

Seeded template keys (by seeder, not hardcoded data-only elsewhere):
`invitation.page.acceptance`, `invitation.email.initial`, `invitation.email.direct`, `invitation.email.resend` (optional resend variants are additional keys enabled by config).

```
content_template_versions (append-only)
  id
  content_template_id  FK, cascadeOnDelete
  version  unsignedInteger NOT NULL          -- 1,2,3…
  payload  json                              -- subject/body/branding/page fields per kind
  placeholders_used json NULL                -- resolved-for-record (never stores submitted user data)
  change_note string(255) NULL               -- editor-supplied change record
  created_by FK → users.id NULL              -- NULL = system seed
  created_at, updated_at
  UNIQUE (content_template_id, version)
```

Restoration = **new version whose payload is a copy** of the chosen old version (no time-travel writes; history immutable).

Why `json` is justified here (per migration rules): heterogeneous per-kind editor payloads whose shape differs by template kind and grows with feature fields — a fixed column set would either be sparse-null-heavy or require migrations per template change. Portable: Laravel `json` ⇒ `jsonb` on PostgreSQL, `TEXT` on SQLite.

## 6.5 CREATE profile-change tables

```
profile_change_requests
  id
  user_id                    FK → users.id, cascadeOnDelete
  status string(30)          'pending' | 'returned_for_correction' | 'partially_approved'
                             | 'approved' | 'cancelled' | 'withdrawn'
  submitted_by               FK → users.id (the user; self-service or admin-assisted)
  cancelled_by               FK NULL; cancelled_reason text NULL
  created_at, updated_at, deleted_at?NO — rows are statusful, never soft-deleted
```

```
profile_change_request_fields          -- CURRENT cycle state, one row per changeable field
  id
  request_id            FK → profile_change_requests.id, cascadeOnDelete
  field                 string(30): 'full_name' | 'contact_number' | 'job_title' | 'department'
  old_value             string(255) NULL      -- snapshot of users.<field> at submission
  old_value_as_of       timestamp             -- snapshot validity
  proposed_value        string(255) NULL
  status                string(30): 'pending' | 'approved' | 'returned'
  decided_by            FK NULL; decided_at timestamp NULL; decision_note text NULL
  UNIQUE (request_id, field)
```

```
profile_change_request_activities      -- append-only; correction + decision + assignment history
  id, request_id FK cascadeOnDelete
  field string(30) NULL                        -- NULL = request-level event
  event string(40)   -- submitted | corrected | approved | returned | withdrawn | cancelled
                     -- reviewer_assigned | reviewer_reassigned | delegation_requested
                     -- delegation_approved | delegation_rejected | escalation_notified
  actor_user_id FK NULL                       -- NULL = system/scheduler
  detail text NULL                            -- human-readable values of that cycle (no PII beyond the fields themselves)
  created_at, updated_at
```

```
profile_change_reviewer_assignments    -- history table; the row with released_at IS NULL is active
  id, request_id FK cascadeOnDelete
  reviewer_id  FK → users.id, restrictOnDelete (history must survive reviewer deletion; nothing soft-deletes reviewers)
  assigned_by  FK NULL
  assigned_at timestamp; released_at timestamp NULL
  release_reason string(30) NULL   -- reassigned | delegation_approved | escalation_reassigned | ineligibility
  override_workload_limit boolean default false
  delegation_status string(20) NULL; delegation_requested_by FK NULL
  delegation_proposed_reviewer_id FK NULL; delegation_decided_by FK NULL; delegation_decided_at timestamp NULL
  created_at, updated_at
```

**Relationship to the existing `User` model (scrutiny point):** requests reference `users.id` and never fork user data. Official values live only on `users.<field>`; proposed values only in field rows. At decision time the service re-compares `users.<field>` with `old_value`; if an admin edited the field in the meantime the request is auto-`returned_for_correction` with an activity note (staleness guard), preventing silent overwrite of newer official data. Approved values are written by the decision transaction; the request completes as `approved`.

## 6.6 CREATE notification tables

```
user_notifications (in-app feed)
  id, user_id FK cascadeOnDelete
  event_key string(60), category string(40)
  title string(150), body text NULL
  link string(255) NULL
  read_at timestamp NULL, created_at, updated_at
  INDEX (user_id, read_at), (user_id, created_at)
```

```
notification_event_configs
  id, event_key string(60) UNIQUE
  email_enabled bool default true, in_app_enabled bool default true
  security_critical bool default false     -- toggles rejected server-side when true
  timestamps
```

```
notification_recipient_rules
  id, event_key string(60)
  recipient_type string(20)  'role' | 'user' | 'invitation_creator'
  recipient_role_code  string(50) NOT NULL default ''   -- sentinel when type != 'role'
  recipient_user_id    FK → users.id NULL ( kept NULL unless type = 'user' )
  UNIQUE (event_key, recipient_type, recipient_role_code, recipient_user_id)
```
Portability note: PostgreSQL treats NULLs as distinct in UNIQUE, SQLite too, but to keep intent explicit the role sentinel is `''` (not NULL); `recipient_user_id` stays NULL and the composite uniqueness is additionally enforced by the repository upsert path (checked within the settings transaction).

```
notification_preferences
  id, user_id FK cascadeOnDelete
  event_key string(60), channel string(20) ('email' | 'in_app'), enabled bool
  UNIQUE (user_id, event_key, channel)
```
Absence of a row = default from `notification_event_configs` (secure-by-default; security-critical events ignore any disabling row).

## 6.7 `users` — unchanged

No new columns on `users`. Contact number/job title/department already exist; `email_verified_at` exists. Full name = `users.name`.

---

# 7. Required Unique Constraints and Indexes

| # | Constraint | Rationale |
| --- | --- | --- |
| 1 | `user_invitations.token_hash` UNIQUE (existing) | token lookup by HMAC; collision-proof. |
| 2 | **Partial UNIQUE** `user_invitations(user_id)` WHERE `accepted_at IS NULL AND revoked_at IS NULL` | one live credential set per account; blocks duplicate pending invitations at the schema level (spec E). Raw portable SQL (both PostgreSQL and SQLite support partial indexes); named index with explicit `down()`. |
| 3 | `email_verification_requests.token_hash` UNIQUE + partial UNIQUE `(user_id, purpose)` WHERE `consumed_at IS NULL` | one live verification per purpose. |
| 4 | `content_templates.template_key` UNIQUE; `content_template_versions` UNIQUE `(content_template_id, version)`; `active_version_id` FK | version integrity + single active version. |
| 5 | `profile_change_requests` partial UNIQUE `(user_id)` WHERE `status IN ('pending','returned_for_correction','partially_approved')` | spec H: one live request per user; portable (SQLite supports IN-lists in partial index WHERE). |
| 6 | `profile_change_request_fields` UNIQUE `(request_id, field)` | one current-cycle row per field. |
| 7 | `notification_preferences` UNIQUE `(user_id, event_key, channel)`; `notification_event_configs.event_key` UNIQUE; recipient-rules composite UNIQUE | preference integrity. |
| 8 | `users.email` UNIQUE (existing, untouched) | the reservation mechanism; blocks issuing credentials for an existing active/deactivated email and duplicate account creation races. |
| 9 | Composite rolling-window indexes on `invitation_link_requests` (§6.2) | O(log n) limit checks. |
| 10 | FK behaviors: history tables (`*_activities`, `*_assignments`, `content_template_versions`) **restrict** or nullable-set-null on parent removal; everything under a user cascades only with the account row itself (permanent deletion = the eventual retention hook, §21 R9). | retention per spec B.10. |

---

# 8. State Machines and Lifecycle Transitions

## 8.1 Invitation credential lifecycle (per `user_invitations` row)

```
created ──(send attempt)──► pending·link+code issued
        ├─ send ok ──► SENT
        │                │── expires_at passed ──► EXPIRED ──(resend: rotate)──► SENT (generation+1)
        │                │
        │                └── send fail ──► DELIVERY FAILED (stays pending; manual retry ≤3/cooldown 5min
        │                                    ⟶ rotate credentials ⟶ SENT)
        └─ cancel (pre-acceptance, from any state) ──► CANCELLED (terminal; credentials instantly dead)
any state where credentials valid ──(accept with valid, unexpired current token; row-locked)──► ACCEPTED (terminal)
```

Status is **derived**: `cancelled` = `revoked_at ≠ null`; `expired` = `expires_at < now ∧ accepted_at null ∧ revoked_at null`; `accepted` = `accepted_at ≠ null`; delivery state = latest `notification_deliveries` row. Old credentials die on rotation via `credential_generation` bump + new `token_hash` + `recovery_code_hash = NULL` (single UPDATE transaction).

## 8.2 Recovery-code sub-machine

```
no code ──(user requests)──► ACTIVE recovery code (hash stored; 15 min)
  ACTIVE ──(correct code)──► REPLACEMENT ISSUED (new link + new code; old link+code invalidated; counters reset)
  ACTIVE ──(wrong code ×5)──► LOCKED 30 min ──(expiry)──► attempts reset rules apply
  ACTIVE ──(15 min pass / rotation / cancellation / acceptance)──► DEAD (hash cleared)
Replacement-via-code NEVER marks accepted_at or touches account activation.
```

## 8.3 Account activation (both methods) — one transaction

```
user: invited ──(acceptance transaction)──► active
   inside ONE DB transaction:
     lock invitation row (lockForUpdate)
     validate token hash + generation + expiry + not consumed/not cancelled
     [review-then-password flow] validate captured required profile fields
     write password (hashed cast)         [acceptance flow per snapshot]
     write email_verified_at (+ ledger row reserved→current)
     invitation.accepted_at = now
     AuditLogger: user.activated / invitation.accepted
   commit ⇒ account may log in (EnsureActiveAccount gate satisfied only after commit)
```
No protected access is possible before this transaction commits, because login requires `account_status='active'` **and** the account is only flag-flipped here.

## 8.4 Template lifecycle

`draft-free`: a save creates version *n+1* (append-only) and moves the active pointer; restore creates version *n+2* copying an old payload. No state column needed; versions are immutable.

## 8.5 Profile-change request lifecycle

```
submitted ──► pending
   ├─ reviewer decision per field: pending → approved | returned
   │      ├─ some approved, others pending/returned ⇒ partially_approved
   │      ├─ all approved ⇒ approved (terminal; users row updated atomically)
   │      └─ any returned ⇒ request returned_for_correction
   │             └─ user edits + resubmits SAME request ⇒ pending again (cycle++ history appended; unlimited)
   ├─ user withdraw ⇒ withdrawn (terminal)
   └─ admin cancel (reason) ⇒ cancelled (terminal)
Assignment sub-state: unassigned ⇄ assigned ≥1 active reviewer row
   delegation: requested ──(admin approve)──► reassignment creates new assignment row
   ineligibility/deactivation: active row released (reason=ineligibility); request → unassigned; admin notified
Escalation: pending-beyond-interval ⇒ per configured mode: notify-only (default) | auto-reassign | notify+permit-manual
```

## 8.6 Reviewer lifecycle events (non-state but safety-relevant)

Reviewer deactivated (existing lifecycle) or removed from eligibility → next touchpoint ( scheduled sweep or on any action attempt) releases their active assignments, blocks further actions server-side, and raises `profile_review.no_reviewer`-style admin notifications. History rows are never deleted or rewritten.

---

# 9. Authorization Matrix

Existing machinery only: Policies + `PermissionChecker` + `permission:` middleware. **Backend-enforced; UI `$can` flags are affordances only.**

Capability | SUPER_ADMIN | SYSTEM_ADMIN | Regular user | Reviewer (eligibility-based) | Project-scoped roles
--- | --- | --- | --- | --- | ---
Create account (direct) | ✔ | ✔ (with `users.create`) | ✖ | ✖ | ✖
Create invitation | ✔ | ✔ (with `users.create`) | ✖ | ✖ | ✖
Choose non-default system role at creation | ✔ (only in non-default mode) | ✖ unless mode permits (see U6) | ✖ | ✖ | ✖
Assign project membership at creation | ✔ | ✔ if holds existing `projects.manage-members` | ✖ | ✖ | ✖
Resend / cancel invitation, manual delivery retry | ✔ | ✔ (with `users.invitations.manage`) | ✖ | ✖ | ✖
Manage templates, profile-field settings, reviewer settings, notification events | ✔ | ✔ (with new `users.templates.manage`, `users.profile-fields.manage`, `users.reviewers.manage`, `users.notifications.manage`) | ✖ | ✖ | ✖
Configure Super-Admin-exclusive settings (default role, SUPER ADMIN role/permissions) | ✔ only | ✖ | ✖ | ✖ | ✖
Permanent account deletion | ✔ (unimplemented today — §21 R9) | ✖ | ✖ | ✖ | ✖
View directory (all states incl. invitations) | ✔ | ✔ (existing directory permission set) | ✖ | ✖ | ✖
Submit profile change (self) | ✔ | ✔ | ✔ | ✔ | ✔
Withdraw own request | ✔ | ✔ | ✔ (own only) | ✔ (own only) | ✔
Approve / return fields | ✔ | reviewer or `users.profile-requests.review` | ✖ | ✔ assigned & eligible only | ✖
Assign / reassign reviewers, approve delegations | ✔ | ✔ (`users.reviewers.manage`) | ✖ | ✖ (delegation request only) | ✖
Cancel others' requests | ✔ | ✔ (`users.profile-requests.cancel`) | ✖ | ✖ | ✖
View others' requests | ✔ | per configured visibility + permissions | own only | assigned only | per configured visibility ⇒ still no action rights
Receive escalation/reminders | ✔ | ✔ per recipient rules | n/a | ✔ own assignments | per recipient rules (notifications ≠ permissions)
Self-approval | prohibited | prohibited | prohibited | prohibited | prohibited

Notes:
- Matrix honours the approved restriction: **recipients of notifications never gain review/assignment permissions** through notification rules.
- New permission strings (proposed, to be seeded to SYSTEM_ADMIN + SUPER_ADMIN by a new `UserManagementExtensionPermissionSeeder`; `users.promote-super-admin` seeded to SUPER_ADMIN only — the seeder refuses attaching it to any other role, mirroring `ProjectAuthorizationPermissionSeeder`'s guard style).
- Security-critical notifications + audit logging always fire; audit writes are not user-toggleable.

---

# 10. Service / Action Class Responsibilities

Thin controllers; one action per business transition; all multi-write operations in a single DB transaction with `AuditLogger` called inside it.

| Class (namespace `App\Actions\Accounts\…` / `App\Services\Accounts\…`) | Responsibility |
| --- | --- |
| `IssueAccountCredentialsAction` | Validates actor permission, field requirements, email availability; creates/uses user row (`account_status='invited'`); creates invitation row + snapshot (`method`, `acceptance_flow`); generates token + optional recovery code; records `invitation_link_requests`; dispatches first send. |
| `ResolveInvitationService` | Token HMAC → row lookup, generation + expiry + revocation checks, row locking, guest/user context resolution. Single point of truth for "is this link usable." |
| `AcceptInvitationAction` | The §8.3 atomic pipeline (both flows + email verification consumption). |
| `RotateInvitationCredentialsService` | Resend / manual retry / recovery-replacement: rate-limit checks (rolling window + cooldown), new token + new code, generation bump, invalidate old, audit, record link request, (re)send email. |
| `IssueRecoveryCodeService` | Generates + hashes + stores code with 15-min expiry; respects format setting; resets attempt counters. |
| `CancelInvitationAction` | Permission + reason capture + `revoked_at/cancelled_by/reason` + audit + notification. |
| `RetryInvitationDeliveryAction` | Wraps `NotificationDeliveryRetryService` (limits/cooldown already enforced there) + credential rotation in one transaction. |
| `EmailVerificationService` | Shared mechanism: issue/resolve/consume verification requests (purposes: `account_activation` now, `email_change` later). |
| `ProfileChange\SubmitService` / `CorrectService` / `WithdrawService` / `CancelService` | Request lifecycle writes + activity appends + staleness checks. |
| `ProfileChange\DecideService` | Per-field approve/return; aggregates request status; applies approval to `users.*` inside the decision transaction; self-approval rejection. |
| `ProfileChange\AssignReviewerService` | Manual/auto strategies; workload cap under lock; delegation decisions; override recording; eligibility evaluation. |
| `ContentTemplate\SaveVersionService` / `RestoreVersionService` | Append-only versioning + pointer flips + audits. |
| `ContentTemplate\Renderer` | Placeholder whitelist substitution + protected-section composition; never accepts unwhitelisted keys. |
| `Accounts\Settings\*Setting` services | Typed setting wrappers on `system_settings` mirroring `EmailLinkExpirationSetting` (defaults+limits, §11.4). |

---

# 11. Controller and Route Design

## 11.1 Public (guest + token; exempt from `EnsureActiveAccount`; heavy throttling)

| Method+URI | Name | Purpose |
| --- | --- | --- |
| GET /invitation/{token} | `invitation.show` | Acceptance page (renders active template; never echoes the raw token). |
| POST /invitation/{token} | `invitation.accept` | Accept flow per snapshot. |
| POST /invitation/{token}/recovery | `invitation.recovery` | Recovery-code entry → replacement link issuance (displayed + emailed). |
| GET /password-setup/{token} | `accounts.password-setup.show` | Direct-method page. |
| POST /password-setup/{token} | `accounts.password-setup.store` | Set password + activate. |

## 11.2 Settings-area (existing `super.admin` group + `permission:` middleware conventions)

| Route (under /settings/users…) | Permission | Purpose |
| --- | --- | --- |
| GET/POST …/create | `users.create` | Direct creation + invitation forms (method, fields per requirements config, optional project membership when permitted). |
| POST …/invitations/{invitation}/resend, /cancel, /retry-delivery | `users.invitations.manage` | Admin credential actions from User Viewing (directory-integrated; no standalone page). |
| GET/PUT …/profile-fields | `users.profile-fields.manage` | Requiredness + visibility + approval-required config. |
| GET/POST …/templates (+ versions, restore) | `users.templates.manage` | Template editor/inspector/restore. |
| GET/PUT …/reviewers | `users.reviewers.manage` | Mode/strategy/cap/eligibility config + manual assignment/delegation approvals. |
| GET …/profile-requests (+ decisions) | view: configured visibility; act: `users.profile-requests.review` / `.cancel` | Request queues. |
| GET/PUT …/notification-events | `users.notifications.manage` | Event/channel/recipient config. |
| user profile self-service routes | authenticated user | Own profile edit + change request submit/withdraw. |

Route names follow the existing `settings.users.*` convention; no changes to existing route names/URIs.

## 11.3 Scheduler (new, in `routes/console.php`)

- `invitations:expire-sweep` — hourly + every minute near expiry boundary handling: fire once-per-invitation `invitation expired` notifications (`expired_notified_at` idempotency).
- `profile-reviews:reminders` — configurable cadence; reviewer reminders email + in-app independently.
- `profile-reviews:escalation` — overdue detection; mode-driven notify-only (default) / auto-reassign / notify+permit-manual.

## 11.4 Settings namespace = typed services mirroring `EmailLinkExpirationSetting`

Key namespaces under `system_settings` (all persisted rows seeded with defaults; every write audited as `settings.changed`):

1. **InvitationSecuritySetting** — `resend_cooldown_seconds=60`, `max_requests_per_invitation_hour=5`, `max_requests_per_ip_hour=10`, `max_recovery_failed_attempts=5`, `recovery_lockout_minutes=30`, `recovery_code_format=numeric` (numeric6 | alphanumeric9).
2. **AcceptanceFlowSetting** — `acceptance_flow=password_setup_only | review_then_password`.
3. **ProfileFieldRequirementSetting / VisibilitySetting / ApprovalSetting** — per-field booleans (email pinned required).
4. **DefaultRoleSetting** — `users.role_assignment_mode=default_only | administrator_selects`, `users.default_role_code` (validated system scope, ≠ SUPER_ADMIN codes).
5. **ReviewerAssignmentSetting** — mode, strategy, default reviewer id, eligibility includes (roles/users), workload cap.
6. **ReminderEscalationSetting** — enable, interval, channels, escalation mode (default `manual_reassignment`), escalation recipient role/user rules.
7. **NotificationEventSetting** — web event toggles live in `notification_event_configs` (table); this service only wraps read access.

Web settings UI: new pages under the existing `super.admin` route group, System Admin accessible per permission (extends today's Super-Admin-only placeholder group — flagged U5).

---

# 12. Blade Page and Component Design

Conventions: existing x-components, dialog component, Tailwind 4 utility patterns, dark-mode class pairing, session-banner usage. No page redesigns outside the module.

1. **Public acceptance/password-setup page** — minimal standalone layout; active template payload renders branding/organization/title/welcome/instructions/supporting-text/contact; protected blocks (expiry notice, security warnings, required steps, password fields, verification instructions, invalid/expired/cancelled/already-used states) are **server-rendered fences the payload cannot omit**.
2. **User Directory** — status filter gains invitation-derived columns: Active, Pending, Expired, Cancelled, Deactivated (existing SoftDeletes-aware filter extended). View and Edit stay separate More-Actions actions; deactivation remains in More Actions; affordances state- and permission-aware.
3. **User Viewing** — hybrid layout extended with an **Invitation Details** read-only panel (state, expiry, sent count, delivery status, cancel/resend/retry actions when permitted). Never renders tokens/codes.
4. **Create/Invite form** — method toggle, fields per requirement settings (server-validated), optional project-membership section for eligible actors, default-role notice when mode = default_only.
5. **Profile self-service + request pages** — pending-request banner if present; old-vs-proposed diff table; correct-and-resubmit; withdraw.
6. **Reviewer queue** — assigned list; per-field decision dialogs; delegation request; workload indicator.
7. **Admin request views** — visibility-filtered; assignment controls; delegation approvals; escalation log.
8. **Templates editor** — list → inspector (versions list, diff-ish payload view, restore button) → editor form per kind with placeholder reference and protected-content note.
9. **Settings config pages** — form pages for each §11.4 namespace; every submit audited.
10. **In-app notifications** — simple dropdown/page feed (unread badge), preferences page per user (event × channel toggles; security-critical disabled toggles shown as locked).

---

# 13. Email Integration Design

1. **Send layer** (new): per recipient+template → `ContentTemplate\Renderer` → `Mailable` (`InvitationLinkMailable`, `DirectAccountMailable` — plain Laravel Mailables, no new deps) → `Mail::to()` **synchronously** → outcome recorded via `NotificationDeliveryRecorder` (`sent` | `failed` + `mail_transport_failed`/`delivery_failed`), retry rows via existing service. `MAIL_MAILER=log` default keeps tests/CI deterministic (logged, recorded as sent-by-design with a test-channel marker — see §17).
2. **Queueing**: spec says don't assume delivery is synchronous or infallible. Design: synchronous send **wrapped in try/catch** whose failure path is a recorded failed delivery + invitation stays pending + Failed status visible + manual retry (spec E exactly). `QUEUE_CONNECTION=database` exists but unused; adopting queues is deferred (flagged U7) — the recorder architecture makes a later queue swap a send-strategy change, not a schema change.
3. **Local infra**: Haraka → Mailpit (SMTP `127.0.0.1:2525`), Podman compose already present — no changes needed for local dev beyond running the stack.
4. **Retention**: deliveries retained (immutable rows); invitation retention follows FK cascade from the user only.

## 13.2 Placeholder model

Per-template whitelist, e.g. initial invite: `{{recipient_name}} {{recipient_email}} {{invitation_link}} {{expiry_minutes}} {{organization_name}} {{actor_name}}`; security-critical sentences (expiry, steps, warnings) appended by the renderer from protected constants, not from payloads. Unknown placeholder keys in a saved payload are rejected at save time.

---

# 14. Notification Design

## 14.1 Event catalog (invitation domain; events configurable unless `security_critical`)

| event_key | Trigger | Default channels | Critical |
| --- | --- | --- | --- |
| `invitation.accepted` | acceptance transaction commits | email+in_app | no |
| `invitation.expired` | scheduler sweep (idempotent) | email+in_app | no |
| `invitation.delivery_failed` | recorder records failed | email+in_app | **yes** |
| `invitation.cancelled` | cancel action | email+in_app | no |
| `invitation.recovery_abuse` | failed-recovery threshold hit/lockout | email+in_app | **yes** |
| `invitation.resent` | rotation issues new send | email+in_app | no |
| `account.activated` | activation completes | email+in_app | no |
| `profile_review.assigned` / `.returned` / `.approved` / `.escalated` / `.no_reviewer` | workflow events | email+in_app | no (`.no_reviewer` treated as admin-critical) |

## 14.2 Recipient resolution + channeling

`notification_recipient_rules` resolve per event → roles / individual users / invitation creator. Channel gating: user preferences (`notification_preferences`) ∩ event config (`notification_event_configs`); **security-critical events ignore disabling rows** and event toggles are server-rejected. Email channel delivers via §13.1; in-app channel writes `user_notifications` rows. Rule changes audited (`settings.changed`).

---

# 15. Audit Event Catalog (via `AuditLogger`, in-transaction)

New namespaced actions (existing style preserved):

- Accounts: `user.account_created`, `invitation.issued`, `invitation.resent`, `invitation.cancelled`, `invitation.accepted`, `user.activated`, `invitation.delivery_retried`, `invitation.recovery_replaced`, `invitation.recovery_failed`, `invitation.lockout_triggered`.
- Templates: `template.version_created`, `template.version_restored`.
- Profile workflow: `profile_change.submitted`, `.corrected`, `.withdrawn`, `.cancelled`, `reviewer.assigned`, `.reassigned`, `.delegation_requested`, `.delegation_approved`, `.delegation_rejected`, `profile_change.field_approved`, `.field_returned`, `.approved`, `.partially_approved`, `profile_change.escalated`.
- Settings: `settings.changed` (namespaced key set in context).
- Preferences: `notification.preferences_changed`, `notification.rules_changed`.

Rules: values that are credentials (tokens/codes/passwords) **never** appear — hashed/absent; `SENSITIVE_KEYS` handles `password`/`token`-style keys; any new key name (`recovery_code`, `token_hash`) added to the list. Every audit call shares the caller's transaction (atomicity spec).

---

# 16. Security and Abuse-Prevention Design

1. **Token model**: raw token = ≥32 random bytes (base64url, ~43 chars); stored ONLY as `hash_hmac('sha256', raw, app_key)`. Deterministic ⇒ index lookups without storing readable secrets; unreadable ⇒ DB dump leaks nothing. Recovery codes: format-dependent (6-digit numeric / 9-char alphanumeric) vs dictionary space — numeric gets the same lockout/limits as alphanumeric (single code per invitation both ways).
2. **Single-use semantics**: consumed/accepted timestamps set only inside the row-locked transaction that performs the effect; resolution re-verifies generation + expiry + not-consumed after acquiring the lock (TOCTOU-safe).
3. **Abuse limits** (spec B defaults): 60 s cooldown between credential requests per invitation; 5 / invitation / hour; 10 / IP / hour; 5 failed recovery attempts → 30-min lockout (configurable). Implemented over the `invitation_link_requests` rolling window + row counters; failed attempts also counted (including rejects).
4. **Creation-time blocks**: existing `active`/deactivated (`soft-deleted` `users`) email ⇒ reject; live request per user unique index ⇒ duplicate pending blocked; cancelled rows are terminal (new rows only).
5. **Secret hygiene**: hashed at rest (§6.1/§6.3); `Hidden` casts; no raw echo in validation errors; audit sanitization (§15); delivery emails contain the raw link — that transport is the only disclosure channel.
6. **Concurrency**: acceptance, rotation, cancellation, reviewer assignment, and workload counting all `lockForUpdate()` the governing row(s) inside one transaction; reviewer workload recounts run under the request-row lock (pattern matches the existing deactivation serialization root).
7. **No protected pre-verification access**: login requires `account_status='active'`; activation completes atomically with verification (§8.3); guest invitation routes only animate the credential they carry.
8. **Authorization**: every write endpoint permission- or policy-gated server-side; visibility settings never relax authorization (matrix §9).

---

# 17. Testing Strategy

PHPUnit 12, `RefreshDatabase` on SQLite `:memory:` (existing suite convention), factories for new models (follow `factories` rules; seeded permission/setting suites in `setUp` per existing feature-test style).

1. **Credential lifecycle**: issue → outbox recorded; accept validates happy path + all rejection branches (expired, cancelled, stale generation, already-consumed); rotation invalidates old tokens (old link 404/410 semantics); resend/retry/cooldown/lockout limits with `Carbon::setTestNow`.
2. **Recovery code**: issue/hash-only storage assert; correct → replacement + old code dead; 5 failures → lockout; code cannot activate (assert `accepted_at`/`account_status` unchanged).
3. **Acceptance & activation**: both flows; snapshot isolation (change settings mid-flight → in-flight invitation keeps its flow); atomicity (assert user active + verified + accepted all-or-nothing via injected failure); no-login-before-activation.
4. **Authorization matrix** (§9 row-by-row): policy + HTTP levels; self-approval server-rejected; recipients ≠ reviewers.
5. **Templates**: versioning append-only; restore creates new version; protected sections present regardless of payload; placeholder whitelist rejection; audits.
6. **Profile workflow**: submission snapshots; staleness auto-return when `users` moved; per-field partial approvals; same-request correction cycles (history rows grow); withdraw/cancel; one-live-request uniqueness.
7. **Reviewer assignment**: strategies, cap enforcement under lock (sequential-interleaving test), manual override recorded, ineligibility release + notifications.
8. **Notifications**: event configs, preferences, security-critical untoggleable; recipient rules; in-app feed rows; delivery recorder integration + manual retry limits (3/5-min, existing service).
9. **Scheduler**: fake time travel; idempotence (`expired_notified_at`).
10. **Concurrency**: acceptance race (two concurrent accepts; only one wins) — SQLite serializes writes, so the race is asserted by repeated interleaved attempts + final-state invariants; true parallelism validated in a small PostgreSQL-targeted run (flagged, R11).
11. **HTTP surface**: guest routes throttled; authentication walls; no raw-token leakage asserts (grep response + logs).

---

# 18. PostgreSQL and SQLite Compatibility

- Append-only migrations (`php artisan make:migration`), never editing deployed ones (rule).
- Portable column types only; no UUIDs; `json` columns only where justified (template payloads, snapshots) — documented rationale per rules.
- Partial UNIQUE indexes written as raw, named SQL statements — supported identically by PostgreSQL and SQLite (incl. `WHERE ... IN` lists), with explicit `down()` drops.
- CHECK constraints on enumerating columns (both engines support).
- FK behaviors chosen from the portable subset (cascade / restrict / set-null on plain columns).
- No engine-specific defaults (no `now()`, no generated columns); Laravel timestamps + model events instead.
- No `JSONB` operators, tsvector, or composite-type usage anywhere.
- Rollback tested both ways during implementation phases (`migrate:fresh` in CI; PostgreSQL run before signing off each schema phase).

---

# 19. Migration Sequence (proposed, dependency-ordered)

1. `add_account_fields_to_user_invitations` — §6.1 columns + partial unique index (#2) + CHECKs.
2. `create_invitation_link_requests_table` — §6.2 (FK → invitations).
3. `create_email_verification_requests_table` — §6.3.
4. `create_content_templates_table` + `create_content_template_versions_table` + `add_active_version_to_content_templates` (3-step for the circular FK) — §6.4.
5. `create_profile_change_requests_table` + `_fields` + `_activities` + `_reviewer_assignments` — §6.5 (single migration or sequential pair per workflow guide).
6. `create_user_notifications_table` + `create_notification_event_configs_table` + `create_notification_recipient_rules_table` + `create_notification_preferences_table` — §6.6.
7. Seeder additions (new seeders, following `SystemSettingSeeder` + permission-seeder patterns): module permissions (§9), default `system_settings` rows (§11.4), template seeds + version 1, notification event configs + default recipient rules.

No changes to existing migrations; `users` untouched.

---

# 20. Phased Implementation Plan (dependencies → order)

| Phase | Scope | Depends on | Ship criteria |
| --- | --- | --- | --- |
| **1. Credential core** | Migrations 1–3; typed security/flow settings; `IssueAccountCredentials`, `Resolve`, `Rotate`, `Issue/verify recovery`, `Cancel`, `EmailVerificationService`; public routes + minimal acceptance/password-setup pages; first send layer + delivery recording; core audits | — | End-to-end invite+direct acceptance green; lifecycle + abuse-limit tests. |
| **2. Admin surfaces** | Directory status-filter integration (pending/expired/cancelled), User Viewing Invitation Details, resend/cancel/retry UI + routes, create/invite forms with role logic (`DefaultRoleSetting`, optional membership) | 1 | Directory + Viewing flows green; matrix tests for §9 admin rows. |
| **3. Templates** | Template tables/seeds, editor/inspector/restore UI, renderer whitelist + protected fences, per-key resend variant option | 1 | Versioning/restore/audit tests; protected-content invariants. |
| **4. Profile-field configuration** | Requirement/visibility/approval settings; enforcement across create/invite/acceptance/admin-edit/self-service | 1 (UI after 2) | Config matrix tests; no-backfill guarantee verified. |
| **5. Profile-change workflow** | §6.5 tables; submit/correct/withdraw/cancel; decision actions; reviewer eligibility + assignment incl. auto strategies, caps, delegation; request visibility filters | 4 | Full H+I coverage; concurrency + staleness tests. |
| **6. Notifications** | §6.6 tables + seeds; in-app feed UI + preferences; recipient rules; security-critical enforcement | 1 (events from 1–5) | Event matrix tests; preference toggling behavior verified. |
| **7. Scheduler** | `routes/console.php` schedules; expiry notifications; reminders; escalations (mode-driven) | 1, 5, 6 | Time-travel tests; idempotence; automation completes spec J. |

Each phase lands with Pint-clean PHP, its phase tests, and the full suite green before the next starts.

---

# 21. Risks, Unresolved Decisions, and Technical Questions

## Conflicts with existing docs/architecture (documented, not silently fixed)

- **R1 — "Reuse the existing email verification system":** no runtime verification exists. The design builds one mechanism (`email_verification_requests`) and modules reuse it; the approved guide's §10 security model is the blueprint. *Decision needed:* none functionally — but the reviewer should confirm "reuse" means "build the shared mechanism now, reuse later for email-change."
- **R2 — Guide §3.3 (administrator manually sets initial password) is superseded** by the approved spec's password-setup links. Documented; guide requires updating if approved (doc change, out of this task's scope).
- **R3 — `account_status='invited'` directory semantics:** invited accounts with live invitations show as Pending; with a cancelled invitation they remain `invited` rows (cannot log in) and display as Cancelled. Confirm this display model is acceptable (alternative: garbage-collect cancelled accounts — rejected: violates retention spec).

## Unresolved decisions (owner calls needed)

- **U4 — Expiration configurability:** spec pins 15-minute expirations while `email_link_expiration_minutes=60` exists. Proposed: module pins 15 min; that setting remains for future email-change. Confirm.
- **U5 — New web settings pages vs Super-Admin-only placeholder group:** spec requires System Admin configuration of many settings. Proposed: extend the settings route group with per-permission access (new `settings.*` view permissions for System Admin). This is a precedent change to the current Super-Admin-only web settings area. Confirm.
- **U6 — Role-selection mode changes:** who may flip `users.role_assignment_mode`? Proposed Super Admin only (spec says Super Admin configures default-restricted role; mode owner ambiguous).
- **U7 — Queue adoption for email sends:** proposed synchronous-with-recording now; queue later. Confirm.
- **U8 — Scheduler adoption:** no scheduler exists today; `routes/console.php` schedules are the minimal Laravel-native choice. Confirm no external cron is expected instead.
- **U9 — Recovery-code loss edge:** single-active recovery code means a lost code + expired link requires admin resend (allowed). Confirm admin resend without recovery code is acceptable to the security model (it is the spec's E.1 path; no extra code needed).

## Risks

- **R10 — Race-fidelity:** SQLite `:memory:` tests serialize writes; concurrency tests prove invariants, not true parallelism. Mitigation: targeted PostgreSQL verification run before sign-off (§17.10).
- **R11 — Permanent deletion absent:** retention "until account permanently deleted" presumes a future Super-Admin-only deletion action; none exists. Tracked as future work — candidate for a separate task; FK cascade design is ready for it.
- **R12 — Placeholder/template escape:** renderer must escape payload content and forbid raw HTML where feasible; protected fences mitigate but editor XSS hygiene needs explicit tests in Phase 3.
- **R13 — In-app notification UI growth:** feed + badge is minimal; deeper consumers (per-page panels) deferred deliberately.
- **R14 — Mail environment:** `MAIL_MAILER=log` default means deliveries "succeed" trivially in dev unless the Haraka/Mailpit stack runs; tests assert recorder rows, not real SMTP (deterministic); documented for reviewers.

## Open technical questions for the reviewer

1. Confirm R1's "reuse = build shared mechanism + reuse" interpretation.
2. Confirm U4–U8 stances above.
3. Confirm directory display semantics R3.
4. Confirm the proposed permission names (§9) before seeder work lands.

---

*End of Phase 1 design. Phase 1 is implemented; see Appendix A for the reconciliation of this design against the shipped implementation. Phases 2–7 await their own approval and task scope.*

---

# Appendix A — Phase 1 implementation reconciliation (2026-11-10)

This appendix records what Phase 1 actually shipped, so the proposal-era names and scopes in the body above are not mistaken for the implementation. The design sections above remain the approved requirement baseline; where a name differs, the implemented name below wins.

## A.1 Implemented naming (supersedes the body where it differs)

| Body name | Implemented name |
| --- | --- |
| `method = 'invite' \| 'direct'` | `user_invitations.method = 'invitation_link' \| 'direct_account_setup'` |
| `GET/POST /invitation/{token}` and `GET/POST /password-setup/{token}` (two pages) | One shared acceptance page for both methods: `GET/POST /invitations/accept/{token}` (`invitations.accept.show` / `invitations.accept.store`) |
| `POST /invitation/{token}/recovery` (`invitation.recovery`) | `GET/POST /invitations/recover` (`invitations.recover.show` / `invitations.recover.store`) |
| `IssueAccountCredentialsAction` / `RotateInvitationCredentialsService` | `App\Actions\IssueUserInvitationAction` / `App\Actions\ResendUserInvitationAction`; recovery wraps resend through `App\Actions\RecoverUserInvitationAction`; acceptance is `App\Actions\AcceptInvitationAction` |
| `invitation.lockout_triggered` | `invitation.recovery_locked` |
| `invitation.recovery_replaced` | `invitation.recovered` |
| `invitation.recovery_failed` | Not audited per failed attempt; failed attempts increment the row-locked `recovery_attempts` counter and an audit is written when the fifth failure locks recovery |
| `invitation.delivery_retried` (Phase 1 name) | `notification.retried` via the existing `NotificationDeliveryRetryService` (no standalone retry route; retry eligibility ships for Phase 2 admin surfaces) |
| Email verification: consume-on-link-open | **Two-step flow (corrective pass 2026-11-10):** `GET /email-verification/verify?token=…` (`email-verification.show`) renders a confirmation page without consuming the token; the CSRF-protected `POST /email-verification/verify` (`email-verification.store`) consumes it. Mail-scanner prefetches can no longer burn a token. |

## A.2 Corrective-pass fixes (2026-11-10)

1. **Stale-link invalidation (was a HIGH defect):** acceptance (`AcceptInvitationAction`, post-lock, inside the transaction) and the acceptance page controller verify the presented link's `credential_generation` equals the invitation's current generation; a superseded link is rejected as invalidated before expiry. Admin resend and recovery-based rotation both produce this behavior. Regression tests cover resend and recovery paths, old-before-new ordering, and single-use/expiry behavior.
2. **Recovery delivery (was a HIGH defect):** after `RecoverUserInvitationAction` commits the credential rotation, `InvitationRecoveryController` sends the replacement link through the existing `InvitationDeliveryService` (no parallel delivery implementation) and the recorder records the outcome; a transport failure never cancels the invitation or undoes the rotation; responses stay neutral; the resend cooldown and rolling limits remain enforced.
3. **Recovery failure counter:** the five-failure lockout counter is now incremented under the invitation row lock inside its own transaction (safe on PostgreSQL).
4. **Recovery-code single use:** a successful recovery consumes the recovery code in the same rotation transaction; consumed, expired, and invalidated codes can no longer authenticate recovery.

## A.3 Closed product decision — recovery-code lifecycle (approved 2026-11-10)

The approved behavior is now final and implemented exactly:

- A successful invitation recovery **consumes the submitted recovery code** (hash and expiry are cleared inside the same row-locked rotation transaction).
- Recovery issues and emails a **replacement invitation link** for the newest credential generation via `InvitationDeliveryService`.
- Recovery **does not issue a replacement recovery code** — no replacement-code issuance exists anywhere.
- A consumed, expired, invalidated, locked-out, or wrong recovery code cannot authenticate another recovery request; every failure is the neutral answer and increments the row-locked attempt counter.
- Recovery never activates an account or grants access.

The body's B.7 wording ("regenerate token_hash, null out recovery hash" as part of formal rotation) is satisfied by the consumption+new-link behavior above; the earlier discrepancy is resolved in favor of the product requirement: recovery requests a replacement invitation link only.

## A.4 Closed product decision — re-invitation after cancellation (approved 2026-11-10; implemented)

An authorized administrator can invite an account again after its previous invitation was cancelled, without deleting or rewriting history:

- Only an `invited` account whose latest invitation is cancelled is eligible; active, deactivated (soft-deleted), and accounts with a still-outstanding invitation are rejected with the same neutral "already registered" answer.
- The cancelled invitation row and its audit and delivery history are preserved byte-for-byte; a **new invitation row** is created on the same user record with fresh token + recovery code at `credential_generation` 1.
- The new lifecycle is independent: the cancelled lifecycle's outstanding credentials (links and code) cannot accept or recover the new invitation — old links resolve to the cancelled invitation and are rejected there; the old code no longer matches the newest lifecyle's hash.
- Normalized-email duplicate protection is preserved (`users.email` UNIQUE plus per-account row locking inside the issuance transaction; parallel duplicate issuances cannot create two live invitations for one account).
- Re-invitation reuses the `POST /settings/users/invitations` route and `users.create` authorization; only `invitation.issued` is audited (with `reissued: true`), never a duplicate `user.account_created`.
- No migration was needed: `user_invitations` already permits multiple rows per `user_id`.

Tests: `tests/Feature/Auth/InvitationReissueTest.php` covers the full cycle, history immutability, dead credentials, duplicate-pending rejection, active/deactivated blocking, repeat cycles, audit singularity, and unauthorized re-invitation (HTTP). PostgreSQL parallel-issuance verification: see Appendix B, race D4.

## A.5 Deferred (per §20; not implemented in Phase 1)

- `DefaultRoleSetting` and any role assignment at creation (Phase 2; new accounts are role-less until Phase 2 decides assignment).
- User Directory / User Viewing UI, account-management UI, and admin resend/cancel/retry affordances (Phase 2). The backend routes and actions already exist.
- Templates, profile-field configuration, profile-change workflows (Phases 3–5).
- Notification event tables and preferences (Phase 6).
- Scheduler and expiry notifications (Phase 7).
- Configurability of recovery-code format (`numeric_6` is fixed) and of the rate-limit knobs (fixed constants in `InvitationRateLimiter`).

## Appendix B — PostgreSQL verification results (2026-11-10)

Target: dedicated database `testops_phase1_test` on the local PostgreSQL 18.6 instance (host 127.0.0.1, port 5432), owned by the application role. The development database `testops` was never written to. Migrations ran there via process-environment overrides only; `.env` and config files are unchanged.

### B.1 Functional parity

- All Phase 1 migrations apply cleanly; `migrate:status` reports every migration `Ran`.
- Smoke pass over every Phase 1 flow (issuance → acceptance → verification request issued at `credential_generation` 1 → audit entries) succeeded.
- Full Phase 1 feature suite: **577/577 tests, 1,675 assertions, 0 failures, ~42 s** on PostgreSQL (initial run surfaced 3 failures, all outside the invitation module, both fixed — see B.3).

### B.2 Concurrency (two independent PHP processes per scenario, PostgreSQL row locks)

| # | Scenario (participants) | Result | Invariant |
|---|---|---|---|
| D1 | Concurrent acceptance of the same link (2 workers) | 1 accepted, 1 rejected (`consumed`) | Exactly 1 acceptance, 1 consumed link, 1 verification request, 1 audit — HELD |
| D2 | Concurrent recovery with the same code (2 workers) | 1 rotated (generation 1→2), 1 neutral failure | Exactly 1 valid link at the newest generation; code consumed once — HELD |
| D3 | Concurrent wrong-code recovery (2 workers × 8 attempts) | Counter increments serialized | `recovery_attempts` = exactly 5 of 16 attempts, 30-min lock active, no bypass/loss — HELD |
| D4 | Concurrent re-issuance of a cancelled invitation (2 workers) | 1 issued, 1 rejected | 2 invitation rows (cancelled history + 1 live), 1 account row — HELD |

First D2 attempt (executed inside the 60-second cooldown) stopped both workers at the rate limiter before rotation — the cooldown is process-external and DB-backed, which itself is correct throttling behavior; the race was re-run after the cooldown window elapsed. Code-verified protections (row-locked rotation, row-locked counter, row-locked eligibility) were exercised against real PostgreSQL locks in all four races.

### B.3 Parity defects found and fixed during verification

1. **PostgreSQL CHECK NULL semantics (real schema defect).** `projects_owner_assignment_check` accepted `owner_id` set with `owner_assignment_type` NULL on PostgreSQL: the check branch evaluated to UNKNOWN, which PostgreSQL (per SQL standard) treats as satisfied, while the SQLite triggers correctly rejected it. Fixed by a new corrective migration `2026_11_10_034039_fix_projects_owner_assignment_check_for_null_semantics` (drop and re-create the constraint requiring a non-NULL assignment type for assigned owners; SQLite unaffected and skips it). This migration is still pending on the development database — apply it there at the next convenient migrate.
2. **Phantom recovery audits (real audit defect).** `RecoverUserInvitationAction` logged `invitation.recovered` before the rotation attempt; a rate-limited or lost rotation race persisted the audit without any recovery. The audit now logs only after the rotation commits. Phantom audits from the pre-fix run remain in the test database as duplicated rows; production data was unaffected.
3. **Test portability (test-only).** `AssignProjectOwnerActionTest` asserted `audit_logs.transfer_type` — a column that does not exist (the value lives in the `after_values` JSON): PostgreSQL raised a missing-column error while SQLite silently evaluated the unknown column to NULL and passed. The assertion now uses the `after_values->transfer_type` JSON path on both drivers.

### B.4 Cleanup

Race/fixture data exists only inside `testops_phase1_test` (unique `*.example.net` addresses). Dropping `testops_phase1_test` is the owner's decision; nothing was dropped during the run.


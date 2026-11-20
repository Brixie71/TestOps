# Graph Report - TestOps  (2026-11-20)

## Corpus Check
- 339 files · ~226,353 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 28 file(s) not represented in the graph (top: (none) 20, .ini 3, .example 2)

## Summary
- 2625 nodes · 6968 edges · 210 communities (52 shown, 158 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 493 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `8da0fdae`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- App\Actions\RequestEmailChangeAction
- DeactivateUserActionTest
- AddProjectMemberActionTest
- ProjectReadHttpTest
- ServiceClientAuthorizationTest
- Illuminate\Foundation\Testing\RefreshDatabase
- EmailVerificationLimitSettings
- AuditLogger
- RoleSeeder
- generate.js
- UserDirectoryTest.php
- Illuminate\Http\Request
- haraka
- EmailVerificationService
- PasswordPolicy
- RemoveProjectMemberActionTest
- Illuminate\Database\Eloquent\Relations\BelongsTo
- Tests\TestCase
- ChangeProjectMemberRoleActionTest
- App\Services\Notifications\InvitationDeliveryService
- AccountSecurityNotificationService
- PasswordChangeTest
- AccountLinkExpirationSettings
- OAuthScopes
- AssignProjectOwnerActionTest
- .members
- UserPasswordManagementTest
- Illuminate\Database\Schema\Blueprint
- PasswordResetRequest
- ProjectAuthorizationPermissionSeederTest
- InvitationDeliveryIntegrationTest
- package.json
- web.php
- .hash
- EnsureActiveAccountMiddlewareTest.php
- UserRoleAssignmentTest
- AuditLogPageTest
- NotificationPreferencesTest
- InvitationLifecycleTest.php
- ProjectOwnershipTest
- UserLifecycleHttpTest
- InvitationLifecycleTest
- UserInvitation
- CredentialHasher
- NotificationDeliveryHistoryTest
- 2026_11_18_000001_add_email_verification_security_controls.php
- ProjectPolicyTest
- EmailVerificationRequest
- NotificationStatusTest
- ProfileUpdateTest
- Carbon\CarbonInterface
- InvitationAcceptanceHttpTest
- InvitationReissueTest
- 2026_11_16_000001_create_password_reset_requests_table.php
- Illuminate\Support\Facades\Mail
- SecurityNotificationTest.php
- InvitationLinkException
- PermissionChecker
- NotificationDelivery
- 2026_11_04_152616_add_account_status_to_users_table.php
- PasswordPolicy
- AdminEmailChangeCancellationTest
- InvitationDeliveryService
- PermissionMiddlewareTest
- EmailChangeTest
- NotificationDeliveryRetryFactory.php
- PasswordResetException
- ProjectFactory.php
- UserFactory
- EmailChangeVerificationTest
- AuditLogController
- UserInvitationFactory.php
- ProjectRelationshipTest
- EmailLinkExpirationAuditTest
- composer.json
- require-dev
- scripts
- Illuminate\Support\Facades\Schema
- Illuminate\Support\Facades\DB
- SettingsAuthorizationTest
- 0001_01_01_000001_create_cache_table.php
- config
- PasswordResetRequestFactory.php
- 2026_11_07_025438_alter_projects_table_for_ownership_foundation.php
- entrypoint.js
- laravel-boost
- 2026_11_04_102853_create_user_email_addresses_table.php
- psr-4
- require
- 2026_11_02_050459_add_role_id_to_users_table.php
- 2026_11_04_070639_add_profile_fields_to_users_table.php
- 2026_11_04_070855_add_deleted_at_to_users_table.php
- Illuminate\Database\Migrations\Migration
- 2026_11_05_074100_create_oauth_access_tokens_table.php
- 2026_11_05_074101_create_oauth_refresh_tokens_table.php
- 2026_11_05_074102_create_oauth_clients_table.php
- 2026_11_05_074103_create_oauth_device_codes_table.php
- 2026_11_05_102745_create_service_client_permissions_table.php
- 2026_11_09_041659_create_email_verification_requests_table.php
- 2026_11_02_051649_create_projects_table.php
- 2026_11_02_045314_create_permissions_table.php
- 2026_11_06_062802_create_notification_delivery_retries_table.php
- App\Models\User
- 2026_11_05_193030_create_user_invitations_table.php
- 2026_11_02_045840_create_permission_role_table.php
- NotificationDeliveryTest
- EmailVerificationRequestFactory
- 2026_11_09_041659_create_invitation_link_requests_table.php
- autoload-dev
- extra
- laravel-boost
- dashboard/index.blade.php
- settings.blade.php
- layouts.app
- projects/index.blade.php
- show.blade.php
- about.blade.php
- settings/index.blade.php
- permissions.blade.php
- roles.blade.php
- 2026_11_20_134007_create_user_notification_preferences_table.php
- UserDetailsPageTest
- layouts.settings
- BulkUserOperationsTest
- PermanentlyDeleteUserActionTest
- UserInvitationHttpTest
- EmailVerificationIssuanceLimitsTest
- App\Http\Controllers\Settings\UserInvitationController
- AuthorizationCodePkceMiddlewareTest
- EmailVerificationLimitSettingsHttpTest
- Illuminate\Database\Eloquent\Factories\Factory
- Illuminate\Database\Eloquent\Builder
- 2026_11_06_055820_create_notification_deliveries_table.php
- bootstrap/app.php
- InvitationLinkRequestFactory
- Illuminate\Http\RedirectResponse
- PermanentUserDeletionHttpTest
- User
- .update
- EmailLinkExpirationAuthorizationTest
- .allows
- InvitationRecoveryHttpTest
- EmailVerificationServiceTest.php
- AuthenticatedShellTest
- NotificationDeliveryRetryTest
- ServiceClientPermissionTest
- AuditLoggerTest
- EmailVerificationRateLimitException
- EmailVerificationHttpTest
- static

## God Nodes (most connected - your core abstractions)
1. `User` - 216 edges
2. `TestCase` - 114 edges
3. `Role` - 89 edges
4. `EmailVerificationService` - 81 edges
5. `Project` - 79 edges
6. `AuditLogger` - 69 edges
7. `ProjectMembershipRules` - 40 edges
8. `AccountLinkExpirationSettings` - 39 edges
9. `RoleSeeder` - 39 edges
10. `DeactivateUserActionTest` - 38 edges

## Surprising Connections (you probably didn't know these)
- `{closure#16}()` --references--> `User`  [EXTRACTED]
  tests/Feature/Settings/UserDirectoryTest.php → app/Models/User.php
- `{closure#1}()` --references--> `Permission`  [EXTRACTED]
  tests/Feature/ProjectRoleSeederTest.php → app/Models/Permission.php
- `DeactivateUserActionTest` --references--> `DeactivateUserAction`  [EXTRACTED]
  tests/Feature/Actions/DeactivateUserActionTest.php → app/Actions/DeactivateUserAction.php
- `EmailVerificationHttpTest` --references--> `EmailVerificationService`  [EXTRACTED]
  tests/Feature/Auth/EmailVerificationHttpTest.php → app/Services/Auth/EmailVerificationService.php
- `EmailVerificationIssuanceLimitsTest` --references--> `EmailVerificationService`  [EXTRACTED]
  tests/Feature/Auth/EmailVerificationIssuanceLimitsTest.php → app/Services/Auth/EmailVerificationService.php

## Import Cycles
- None detected.

## Communities (210 total, 158 thin omitted)

### Community 0 - "App\Actions\RequestEmailChangeAction"
Cohesion: 0.13
Nodes (3): {closure#2}(), RequestEmailChangeAction, ProfileController

### Community 1 - "DeactivateUserActionTest"
Cohesion: 0.10
Nodes (3): RevokesUserCredentials, DeactivateUserActionTest, RevokesUserCredentialsTest

### Community 4 - "ServiceClientAuthorizationTest"
Cohesion: 0.06
Nodes (4): AuthorizationCodePkceTest, OAuthScopeIssuanceTest, ServiceClientAuthorizationTest, AccountLinkExpirationHttpTest

### Community 5 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.07
Nodes (7): AuditLog, Permission, OAuthScopes, ExampleTest, AuditLogTest, UserEmailAddressFactoryTest, TestCase

### Community 6 - "EmailVerificationLimitSettings"
Cohesion: 0.29
Nodes (3): SystemSettingEmailVerificationLimitController, SystemSettingEmailVerificationLimitResource, EmailVerificationLimitSettings

### Community 7 - "AuditLogger"
Cohesion: 0.07
Nodes (27): AddProjectMemberAction, {closure#1}(), AssignProjectOwnerAction, {closure#1}(), {closure#2}(), ChangeProjectAutoAssignmentSettingAction, {closure#1}(), ChangeProjectMemberRoleAction (+19 more)

### Community 8 - "RoleSeeder"
Cohesion: 0.09
Nodes (7): Role, ProjectAuthorizationPermissionSeeder, ProjectRoleSeeder, RoleSeeder, {closure#1}(), ProjectRoleSeederTest, RoleSeederTest

### Community 9 - "generate.js"
Cohesion: 0.06
Nodes (23): allNoBorders, ASSETS, body, BODY_FONT, bodyMargin, buildCoverR1(), bullet(), calcCoverSpacing() (+15 more)

### Community 11 - "Illuminate\Http\Request"
Cohesion: 0.14
Nodes (4): InvitationRecoveryController, AuthenticatedSessionController, Controller, ProjectController

### Community 12 - "haraka"
Cohesion: 0.06
Nodes (31): context, dockerfile, driver, DELIVERY_MODE, HARAKA_SMTP_PASSWORD, HARAKA_SMTP_USER, MP_DATABASE, MP_MAX_MESSAGES (+23 more)

### Community 13 - "EmailVerificationService"
Cohesion: 0.24
Nodes (4): {closure#1}(), {closure#1}(), EmailVerificationService, EmailVerificationServiceTest

### Community 17 - "Tests\TestCase"
Cohesion: 0.09
Nodes (5): EmailVerificationAttemptCounter, {closure#1}(), UserNotificationPreference, {closure#1}(), {closure#1}()

### Community 19 - "App\Services\Notifications\InvitationDeliveryService"
Cohesion: 0.10
Nodes (11): DirectAccountSetupMailable, EmailVerificationMailable, InvitationLinkMailable, PasswordChangedMailable, PasswordResetMailable, {closure#1}(), {closure#1}(), {closure#2}() (+3 more)

### Community 22 - "AccountLinkExpirationSettings"
Cohesion: 0.26
Nodes (3): {closure#2}(), AccountLinkExpirationSettings, AccountLinkExpirationSettingsTest

### Community 23 - "OAuthScopes"
Cohesion: 0.10
Nodes (4): AppServiceProvider, {closure#1}(), ScopeRepository, PassportConfigurationTest

### Community 24 - "AssignProjectOwnerActionTest"
Cohesion: 0.09
Nodes (3): AssignProjectOwnerActionTest, ChangeAutoAssignmentSettingActionTest, UnassignProjectOwnerActionTest

### Community 25 - ".members"
Cohesion: 0.06
Nodes (4): ChangeProjectOwnerAssignmentTypeActionTest, TransferProjectOwnerActionTest, ProjectMembershipHttpTest, ProjectOwnershipHttpTest

### Community 27 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.13
Nodes (10): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}() (+2 more)

### Community 31 - "package.json"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 40 - "UserLifecycleHttpTest"
Cohesion: 0.10
Nodes (3): UserManagementPermissionSeeder, AdminProfileUpdateTest, UserLifecycleHttpTest

### Community 42 - "UserInvitation"
Cohesion: 0.18
Nodes (3): {closure#1}(), {closure#1}(), UserInvitation

### Community 43 - "CredentialHasher"
Cohesion: 0.25
Nodes (6): {closure#1}(), IssueUserInvitationAction, CredentialHasher, InvitationCredentialGenerator, InvitationLinkRequestIssuer, InvitationRateLimiter

### Community 45 - "2026_11_18_000001_add_email_verification_security_controls.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 47 - "EmailVerificationRequest"
Cohesion: 0.15
Nodes (8): {closure#1}(), {closure#1}(), {closure#2}(), EmailChangeVerificationController, EmailVerificationController, InvitationAcceptanceController, EmailVerificationRequest, UserEmailAddress

### Community 53 - "2026_11_16_000001_create_password_reset_requests_table.php"
Cohesion: 0.33
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), createOutstandingRequestUniqueIndex(), up()

### Community 56 - "SecurityNotificationTest.php"
Cohesion: 0.11
Nodes (8): AccountDeactivatedMailable, AccountReactivatedMailable, SystemRoleChangedMailable, SecurityNotificationRetryService, {closure#1}(), {closure#2}(), {closure#3}(), SecurityNotificationTest

### Community 58 - "PermissionChecker"
Cohesion: 0.10
Nodes (7): {closure#1}(), EnforceAuthorizationCodePkce, EnsureEmailLinkExpirationAccess, EnsurePermission, EnsureUserIsSuperAdmin, PermissionChecker, ServiceClientAuthorizer

### Community 59 - "NotificationDelivery"
Cohesion: 0.06
Nodes (12): NotificationDeliveryHistoryController, NotificationDelivery, NotificationDeliveryRetry, {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), NotificationDeliveryRetryService (+4 more)

### Community 61 - "PasswordPolicy"
Cohesion: 0.23
Nodes (4): ForceUserPasswordResetAction, SetUserPasswordAction, UserPasswordController, PasswordPolicy

### Community 63 - "InvitationDeliveryService"
Cohesion: 0.12
Nodes (6): EmailChangeVerificationMailable, SystemSetting, InvitationDeliveryService, NotificationDeliveryRecorder, {closure#1}(), NotificationDeliveryRecorderTest

### Community 67 - "PasswordResetException"
Cohesion: 0.12
Nodes (7): {closure#2}(), CompleteForcedPasswordResetAction, {closure#1}(), ForcedPasswordResetController, PasswordResetRequest, PasswordResetException, PasswordResetService

### Community 75 - "composer.json"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 76 - "require-dev"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision (+1 more)

### Community 77 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 78 - "Illuminate\Support\Facades\Schema"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 79 - "Illuminate\Support\Facades\DB"
Cohesion: 0.07
Nodes (7): AccountCreationPermissionSeeder, AccountLinkExpirationSettingsSeeder, DatabaseSeeder, SuperAdminSeeder, SystemSettingPermissionSeeder, SystemSettingSeeder, TestUserSeeder

### Community 82 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 84 - "2026_11_07_025438_alter_projects_table_for_ownership_foundation.php"
Cohesion: 0.38
Nodes (6): addOwnerAssignmentConstraint(), {closure#1}(), {closure#2}(), down(), dropOwnerAssignmentConstraint(), up()

### Community 85 - "entrypoint.js"
Cohesion: 0.29
Nodes (4): authConfig, fs, haraka, { spawn, spawnSync }

### Community 86 - "laravel-boost"
Cohesion: 0.29
Nodes (6): command, enabled, type, mcp, laravel-boost, $schema

### Community 88 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 89 - "require"
Cohesion: 0.33
Nodes (6): require, barryvdh/laravel-dompdf, laravel/framework, laravel/passport, laravel/tinker, php

### Community 101 - "2026_11_09_041659_create_email_verification_requests_table.php"
Cohesion: 0.50
Nodes (3): {closure#1}(), createOutstandingRequestUniqueIndex(), up()

### Community 106 - "App\Models\User"
Cohesion: 0.08
Nodes (15): {closure#1}(), CancelEmailChangeAction, {closure#1}(), {closure#1}(), PermanentlyDeleteUserAction, {closure#1}(), {closure#2}(), {closure#1}() (+7 more)

### Community 114 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 115 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 174 - "PermanentlyDeleteUserActionTest"
Cohesion: 0.09
Nodes (4): LifecycleConnectionGuard, PermanentlyDeleteUserActionTest, ReactivateUserActionTest, LifecycleConnectionGuardTest

### Community 187 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.07
Nodes (27): {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+19 more)

### Community 192 - "bootstrap/app.php"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 194 - "Illuminate\Http\RedirectResponse"
Cohesion: 0.12
Nodes (8): AssignUserRoleAction, DeactivateUserAction, ReactivateUserAction, BulkUserController, {closure#2}(), {closure#3}(), UserLifecycleController, UserRoleController

### Community 196 - "User"
Cohesion: 0.08
Nodes (4): Project, User, ProjectPolicy, {closure#4}()

### Community 197 - ".update"
Cohesion: 0.17
Nodes (3): SystemSettingAccountLinkExpirationController, SystemSettingAccountLinkExpirationResource, {closure#1}()

## Knowledge Gaps
- **10 isolated node(s):** `layouts.app`, `php`, `layouts.app`, `layouts.app`, `layouts.app` (+5 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 661 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **158 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `ProjectReadHttpTest`, `ServiceClientAuthorizationTest`, `Illuminate\Foundation\Testing\RefreshDatabase`, `AuditLogger`, `RoleSeeder`, `UserDirectoryTest.php`, `Illuminate\Http\Request`, `RemoveProjectMemberActionTest`, `Tests\TestCase`, `ChangeProjectMemberRoleActionTest`, `AccountLinkExpirationSettings`, `AssignProjectOwnerActionTest`, `.members`, `InvitationDeliveryIntegrationTest`, `.hash`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `InvitationLifecycleTest.php`, `UserInvitation`, `CredentialHasher`, `UserDetailsPageTest`, `PermanentlyDeleteUserActionTest`, `EmailVerificationRequest`, `ProjectPolicyTest`, `UserInvitationHttpTest`, `Carbon\CarbonInterface`, `App\Http\Controllers\Settings\UserInvitationController`, `InvitationAcceptanceHttpTest`, `InvitationReissueTest`, `PermissionChecker`, `NotificationDelivery`, `Illuminate\Database\Eloquent\Builder`, `InvitationDeliveryService`, `PermissionMiddlewareTest`, `Illuminate\Http\RedirectResponse`, `PasswordResetException`, `ProjectFactory.php`, `UserFactory`, `EmailLinkExpirationAuthorizationTest`, `.allows`, `EmailLinkExpirationAuditTest`, `Illuminate\Support\Facades\DB`, `SettingsAuthorizationTest`, `EmailVerificationHttpTest`, `UserInvitationFactory.php`, `App\Models\User`?**
  _High betweenness centrality (0.208) - this node is a cross-community bridge._
- **What connects `layouts.app`, `php`, `layouts.app` to the rest of the system?**
  _10 weakly-connected nodes found - possible missing edges._
- **Should `App\Actions\RequestEmailChangeAction` be split into smaller, more focused modules?**
  _Cohesion score 0.13405797101449277 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `ProjectReadHttpTest`, `ServiceClientAuthorizationTest`, `AuditLogger`, `RoleSeeder`, `UserDirectoryTest.php`, `EmailVerificationService`, `RemoveProjectMemberActionTest`, `Tests\TestCase`, `ChangeProjectMemberRoleActionTest`, `AccountLinkExpirationSettings`, `OAuthScopes`, `AssignProjectOwnerActionTest`, `.members`, `ProjectAuthorizationPermissionSeederTest`, `InvitationDeliveryIntegrationTest`, `.hash`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `InvitationLifecycleTest.php`, `ProjectOwnershipTest`, `InvitationLifecycleTest`, `UserDetailsPageTest`, `PermanentlyDeleteUserActionTest`, `ProjectPolicyTest`, `UserInvitationHttpTest`, `InvitationAcceptanceHttpTest`, `InvitationReissueTest`, `AuthorizationCodePkceMiddlewareTest`, `NotificationDelivery`, `InvitationDeliveryService`, `PermissionMiddlewareTest`, `EmailLinkExpirationAuthorizationTest`, `ProjectRelationshipTest`, `EmailLinkExpirationAuditTest`, `InvitationRecoveryHttpTest`, `.allows`, `Illuminate\Support\Facades\DB`, `SettingsAuthorizationTest`, `AuthenticatedShellTest`, `NotificationDeliveryRetryTest`, `ServiceClientPermissionTest`, `AuditLoggerTest`, `EmailVerificationHttpTest`, `NotificationDeliveryTest`?**
  _High betweenness centrality (0.114) - this node is a cross-community bridge._
- **Should `DeactivateUserActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.1 - nodes in this community are weakly interconnected._
- **Why does `AssignProjectOwnerActionTest` connect `AssignProjectOwnerActionTest` to `RoleSeeder`, `Illuminate\Foundation\Testing\RefreshDatabase`?**
  _High betweenness centrality (0.026) - this node is a cross-community bridge._
- **Should `AddProjectMemberActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.12303422756706753 - nodes in this community are weakly interconnected._
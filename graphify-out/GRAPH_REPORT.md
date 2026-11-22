# Graph Report - TestOps  (2026-11-22)

## Corpus Check
- 349 files · ~230,617 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 28 file(s) not represented in the graph (top: (none) 20, .ini 3, .example 2)

## Summary
- 2711 nodes · 7162 edges · 225 communities (54 shown, 171 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 499 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `2585df43`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- NotificationPreferenceEnforcementTest.php
- DeactivateUserActionTest
- AddProjectMemberActionTest
- CredentialHasher
- AccountLinkExpirationHttpTest
- Illuminate\Foundation\Testing\RefreshDatabase
- AssignProjectOwnerActionTest
- AuditLogger
- RoleSeeder
- generate.js
- UserDirectoryTest.php
- UpdateUserProfileAction
- haraka
- EmailVerificationService
- PasswordPolicy
- RemoveProjectMemberActionTest
- Illuminate\Database\Eloquent\Model
- RbacOverviewTest
- ServiceClientAuthorizationTest
- Illuminate\Mail\Mailable
- ReactivateUserActionTest
- Illuminate\Database\Eloquent\Factories\Factory
- AccountLinkExpirationSettings
- OAuthScopes
- PermissionChecker
- .members
- UserPasswordManagementTest
- Illuminate\Database\Schema\Blueprint
- PasswordResetRequest
- App\Models\User
- TransferProjectOwnerActionTest
- package.json
- web.php
- PasswordPolicy
- EnsureActiveAccountMiddlewareTest.php
- UserRoleAssignmentTest
- AuditLogPageTest
- NotificationPreferencesTest
- NotificationDeliveryRetryServiceTest.php
- ProjectOwnershipTest
- UserLifecycleHttpTest
- InvitationLifecycleTest
- EmailVerificationLimitSettings
- UserInvitation
- NotificationDeliveryHistoryTest
- ProjectPolicyTest
- ProjectOwnershipTest.php
- RemoveProjectMemberActionTest
- NotificationStatusTest
- App\Services\Audit\AuditLogger
- InvitationLifecycleTest.php
- InvitationAcceptanceHttpTest
- InvitationReissueTest
- 2026_11_16_000001_create_password_reset_requests_table.php
- NotificationDelivery
- DeactivateUserActionTest.php
- AccountReactivatedMailable
- Laravel\Passport\Client
- .allows
- EmailLinkExpirationSetting
- App\Services\Audit\AuditLogger
- App\Actions\AcceptInvitationAction
- AdminEmailChangeCancellationTest
- .recordFailure
- PermissionMiddlewareTest
- EmailChangeTest
- NotificationDeliveryRetryFactory.php
- .log
- ProjectFactory.php
- UserFactory
- EmailChangeVerificationTest
- .retry
- UserInvitationFactory.php
- ProjectRelationshipTest
- EmailLinkExpirationAuditTest
- composer.json
- require-dev
- scripts
- Illuminate\Database\Migrations\Migration
- Illuminate\Database\Seeder
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
- RoleManagementTest
- NotificationDeliveryRetry
- InvitationDeliveryIntegrationTest
- 2026_11_05_074059_create_oauth_auth_codes_table.php
- 2026_11_05_074100_create_oauth_access_tokens_table.php
- 2026_11_05_074101_create_oauth_refresh_tokens_table.php
- 2026_11_05_074102_create_oauth_clients_table.php
- 2026_11_05_074103_create_oauth_device_codes_table.php
- 2026_11_05_102745_create_service_client_permissions_table.php
- 2026_11_09_041659_create_email_verification_requests_table.php
- 2026_11_02_051649_create_projects_table.php
- UserEmailAddressFactoryTest
- 2026_11_06_062802_create_notification_delivery_retries_table.php
- App\Models\NotificationDelivery
- Illuminate\Support\Facades\Schema
- 2026_11_06_055820_create_notification_deliveries_table.php
- NotificationDeliveryTest
- EmailVerificationRequestFactory
- .hash
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
- InvitationLinkRequestFactory
- bootstrap/app.php
- UserDetailsPageTest
- Database\Factories\UserFactory
- layouts.settings
- BulkUserOperationsTest
- PermanentlyDeleteUserActionTest
- AuditLogTest
- UserInvitationHttpTest
- AdminProfileUpdateTest
- TestSuiteTest
- AuthorizationCodePkceMiddlewareTest
- EmailVerificationLimitSettingsHttpTest
- AuthorizationCodePkceTest
- Illuminate\Database\Eloquent\Builder
- 2026_11_04_152616_add_account_status_to_users_table.php
- 2026_11_20_134700_add_suppression_reason_to_notification_deliveries_table.php
- PasswordChangeTest
- 2026_11_09_041659_extend_user_invitations_table_for_account_creation_module.php
- 2026_11_02_044815_create_roles_table.php
- AccountSecurityNotificationService
- Illuminate\Http\Request
- PermanentUserDeletionHttpTest
- Project
- {closure#1}
- 2026_11_02_074820_create_project_user_table.php
- 2026_11_09_041659_make_users_name_nullable.php
- ProfileUpdateTest
- 2026_11_02_045314_create_permissions_table.php
- InvitationRecoveryHttpTest
- Illuminate\Contracts\View\View
- OAuthScopeIssuanceTest
- App\Http\Controllers\SystemSettingAccountLinkExpirationController
- InvitationLinkMailable
- 2026_11_05_005052_create_system_settings_table.php
- AuthenticatedShellTest
- NotificationDeliveryRetryTest
- 2026_11_20_135544_create_test_suites_table.php
- AuditLoggerTest
- Illuminate\Support\Facades\Mail
- audit-logs.blade.php
- user-details.blade.php

## God Nodes (most connected - your core abstractions)
1. `User` - 210 edges
2. `TestCase` - 112 edges
3. `Role` - 87 edges
4. `EmailVerificationService` - 81 edges
5. `Project` - 81 edges
6. `AuditLogger` - 69 edges
7. `NotificationDelivery` - 43 edges
8. `ProjectMembershipRules` - 40 edges
9. `AccountLinkExpirationSettings` - 39 edges
10. `RoleSeeder` - 39 edges

## Surprising Connections (you probably didn't know these)
- `{closure#1}()` --references--> `PasswordChangedMailable`  [EXTRACTED]
  tests/Feature/Profile/PasswordChangeTest.php → app/Mail/PasswordChangedMailable.php
- `{closure#16}()` --references--> `User`  [EXTRACTED]
  tests/Feature/Settings/UserDirectoryTest.php → app/Models/User.php
- `{closure#1}()` --references--> `NotificationDelivery`  [EXTRACTED]
  tests/Feature/Services/Notifications/NotificationDeliveryRetryServiceTest.php → app/Models/NotificationDelivery.php
- `DeactivateUserActionTest` --references--> `DeactivateUserAction`  [EXTRACTED]
  tests/Feature/Actions/DeactivateUserActionTest.php → app/Actions/DeactivateUserAction.php
- `PermanentlyDeleteUserActionTest` --references--> `PermanentlyDeleteUserAction`  [EXTRACTED]
  tests/Feature/Actions/PermanentlyDeleteUserActionTest.php → app/Actions/PermanentlyDeleteUserAction.php

## Import Cycles
- None detected.

## Communities (225 total, 171 thin omitted)

### Community 1 - "DeactivateUserActionTest"
Cohesion: 0.10
Nodes (3): RevokesUserCredentials, DeactivateUserActionTest, RevokesUserCredentialsTest

### Community 2 - "AddProjectMemberActionTest"
Cohesion: 0.09
Nodes (3): AddProjectMemberActionTest, EmailLinkExpirationAuthorizationTest, ProjectPolicyAbilitiesTest

### Community 3 - "CredentialHasher"
Cohesion: 0.14
Nodes (6): IssueUserInvitationAction, ResendUserInvitationAction, CredentialHasher, InvitationCredentialGenerator, InvitationLinkRequestIssuer, InvitationRateLimiter

### Community 5 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.07
Nodes (8): AuditLog, Permission, Role, User, OAuthScopes, ExampleTest, {closure#1}(), TestCase

### Community 7 - "AuditLogger"
Cohesion: 0.15
Nodes (12): AddProjectMemberAction, AssignProjectOwnerAction, ChangeProjectAutoAssignmentSettingAction, ChangeProjectMemberRoleAction, ChangeProjectOwnerAssignmentTypeAction, RemoveProjectMemberAction, TransferProjectOwnerAction, UnassignProjectOwnerAction (+4 more)

### Community 8 - "RoleSeeder"
Cohesion: 0.09
Nodes (7): ProjectAuthorizationPermissionSeeder, ProjectRoleSeeder, RoleSeeder, UserManagementPermissionSeeder, ProjectAuthorizationPermissionSeederTest, ProjectRoleSeederTest, RoleSeederTest

### Community 9 - "generate.js"
Cohesion: 0.06
Nodes (23): allNoBorders, ASSETS, body, BODY_FONT, bodyMargin, buildCoverR1(), bullet(), calcCoverSpacing() (+15 more)

### Community 12 - "haraka"
Cohesion: 0.06
Nodes (31): context, dockerfile, driver, DELIVERY_MODE, HARAKA_SMTP_PASSWORD, HARAKA_SMTP_USER, MP_DATABASE, MP_MAX_MESSAGES (+23 more)

### Community 13 - "EmailVerificationService"
Cohesion: 0.07
Nodes (13): {closure#1}(), {closure#1}(), CompleteAccountActivationAction, {closure#1}(), {closure#2}(), {closure#1}(), EmailVerificationController, UserEmailAddress (+5 more)

### Community 15 - "RemoveProjectMemberActionTest"
Cohesion: 0.10
Nodes (3): ChangeAutoAssignmentSettingActionTest, ChangeProjectMemberRoleActionTest, UnassignProjectOwnerActionTest

### Community 16 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.08
Nodes (7): EmailVerificationAttemptCounter, EmailVerificationRequest, InvitationLinkRequest, {closure#1}(), ServiceClientPermission, TestSuite, UserNotificationPreference

### Community 19 - "Illuminate\Mail\Mailable"
Cohesion: 0.10
Nodes (9): DirectAccountSetupMailable, EmailChangeCancelledMailable, EmailChangeVerificationMailable, EmailVerificationMailable, PasswordChangedMailable, PasswordResetMailable, {closure#2}(), {closure#3}() (+1 more)

### Community 20 - "ReactivateUserActionTest"
Cohesion: 0.13
Nodes (3): LifecycleConnectionGuard, ReactivateUserActionTest, LifecycleConnectionGuardTest

### Community 22 - "AccountLinkExpirationSettings"
Cohesion: 0.21
Nodes (3): SystemSettingAccountLinkExpirationController, AccountLinkExpirationSettings, AccountLinkExpirationSettingsTest

### Community 23 - "OAuthScopes"
Cohesion: 0.10
Nodes (4): AppServiceProvider, {closure#1}(), ScopeRepository, PassportConfigurationTest

### Community 24 - "PermissionChecker"
Cohesion: 0.14
Nodes (5): {closure#1}(), EnforceAuthorizationCodePkce, EnsurePermission, EnsureUserIsSuperAdmin, PermissionChecker

### Community 25 - ".members"
Cohesion: 0.05
Nodes (4): ChangeProjectOwnerAssignmentTypeActionTest, ProjectMembershipHttpTest, ProjectOwnershipHttpTest, ProjectReadHttpTest

### Community 27 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.10
Nodes (13): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}() (+5 more)

### Community 29 - "App\Models\User"
Cohesion: 0.08
Nodes (11): {closure#1}(), CancelEmailChangeAction, {closure#1}(), {closure#1}(), {closure#1}(), {closure#2}(), RequestEmailChangeAction, {closure#2}() (+3 more)

### Community 31 - "package.json"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 33 - "PasswordPolicy"
Cohesion: 0.30
Nodes (4): ForceUserPasswordResetAction, SetUserPasswordAction, UserPasswordController, PasswordPolicy

### Community 42 - "EmailVerificationLimitSettings"
Cohesion: 0.10
Nodes (6): SystemSettingEmailVerificationLimitController, SystemSettingAccountLinkExpirationResource, SystemSettingEmailLinkExpirationResource, SystemSettingEmailVerificationLimitResource, EmailVerificationRateLimitException, EmailVerificationLimitSettings

### Community 43 - "UserInvitation"
Cohesion: 0.18
Nodes (5): CancelUserInvitationAction, {closure#1}(), {closure#1}(), UserInvitationController, UserInvitation

### Community 49 - "App\Services\Audit\AuditLogger"
Cohesion: 0.22
Nodes (5): DeactivateUserAction, PermanentlyDeleteUserAction, ReactivateUserAction, UserLifecycleController, UserDeletionDependencyInspector

### Community 50 - "InvitationLifecycleTest.php"
Cohesion: 0.18
Nodes (3): AcceptInvitationAction, RecoverUserInvitationAction, InvitationRateLimitException

### Community 53 - "2026_11_16_000001_create_password_reset_requests_table.php"
Cohesion: 0.33
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), createOutstandingRequestUniqueIndex(), up()

### Community 54 - "NotificationDelivery"
Cohesion: 0.13
Nodes (8): {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}(), NotificationDelivery, {closure#1}(), {closure#2}(), {closure#3}()

### Community 55 - "DeactivateUserActionTest.php"
Cohesion: 0.13
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 56 - "AccountReactivatedMailable"
Cohesion: 0.11
Nodes (8): AccountDeactivatedMailable, AccountReactivatedMailable, SystemRoleChangedMailable, SecurityNotificationRetryService, {closure#1}(), {closure#2}(), {closure#3}(), SecurityNotificationTest

### Community 58 - ".allows"
Cohesion: 0.13
Nodes (3): ProjectController, UserDirectoryController, PermissionCheckerTest

### Community 59 - "EmailLinkExpirationSetting"
Cohesion: 0.17
Nodes (4): SystemSettingEmailLinkExpirationController, SystemSetting, EmailLinkExpirationSetting, EmailLinkExpirationSettingTest

### Community 60 - "App\Services\Audit\AuditLogger"
Cohesion: 0.13
Nodes (7): {closure#2}(), CompleteForcedPasswordResetAction, {closure#1}(), ForcedPasswordResetController, PasswordResetRequest, PasswordResetException, PasswordResetService

### Community 63 - ".recordFailure"
Cohesion: 0.18
Nodes (3): NotificationDeliveryRecorder, {closure#1}(), NotificationDeliveryRecorderTest

### Community 67 - ".log"
Cohesion: 0.17
Nodes (12): {closure#1}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}() (+4 more)

### Community 75 - "composer.json"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 76 - "require-dev"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision (+1 more)

### Community 77 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 79 - "Illuminate\Database\Seeder"
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

### Community 93 - "NotificationDeliveryRetry"
Cohesion: 0.17
Nodes (4): NotificationDeliveryHistoryController, NotificationDeliveryRetry, {closure#2}(), NotificationDeliveryRetryService

### Community 101 - "2026_11_09_041659_create_email_verification_requests_table.php"
Cohesion: 0.50
Nodes (3): {closure#1}(), createOutstandingRequestUniqueIndex(), up()

### Community 107 - "Illuminate\Support\Facades\Schema"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 114 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 115 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 127 - "bootstrap/app.php"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 187 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.06
Nodes (30): {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+22 more)

### Community 194 - "Illuminate\Http\Request"
Cohesion: 0.09
Nodes (11): AssignUserRoleAction, AuthenticatedSessionController, Controller, ProfileController, BulkUserController, {closure#2}(), {closure#3}(), {closure#2}() (+3 more)

### Community 205 - "Illuminate\Contracts\View\View"
Cohesion: 0.13
Nodes (5): CompleteEmailChangeAction, EmailChangeVerificationController, InvitationAcceptanceController, InvitationRecoveryController, PermissionController

### Community 212 - "InvitationLinkMailable"
Cohesion: 0.33
Nodes (3): InvitationLinkMailable, {closure#1}(), {closure#1}()

## Knowledge Gaps
- **11 isolated node(s):** `layouts.settings`, `layouts.settings`, `php`, `layouts.app`, `layouts.app` (+6 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 685 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **171 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `NotificationPreferenceEnforcementTest.php`, `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `CredentialHasher`, `AccountLinkExpirationHttpTest`, `AssignProjectOwnerActionTest`, `AuditLogger`, `RoleSeeder`, `UserDirectoryTest.php`, `EmailVerificationService`, `RemoveProjectMemberActionTest`, `Illuminate\Database\Eloquent\Model`, `ServiceClientAuthorizationTest`, `ReactivateUserActionTest`, `AccountLinkExpirationSettings`, `.members`, `App\Models\User`, `TransferProjectOwnerActionTest`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `NotificationDeliveryRetryServiceTest.php`, `UserInvitation`, `Database\Factories\UserFactory`, `ProjectPolicyTest`, `ProjectOwnershipTest.php`, `RemoveProjectMemberActionTest`, `App\Services\Audit\AuditLogger`, `InvitationLifecycleTest.php`, `InvitationAcceptanceHttpTest`, `InvitationReissueTest`, `UserInvitationHttpTest`, `DeactivateUserActionTest.php`, `Laravel\Passport\Client`, `.allows`, `Illuminate\Database\Eloquent\Builder`, `EmailLinkExpirationSetting`, `App\Actions\AcceptInvitationAction`, `AuthorizationCodePkceTest`, `Illuminate\Http\Request`, `.log`, `Project`, `ProjectFactory.php`, `.retry`, `EmailLinkExpirationAuditTest`, `Illuminate\Contracts\View\View`, `Illuminate\Database\Seeder`, `SettingsAuthorizationTest`, `NotificationDeliveryRetry`, `InvitationDeliveryIntegrationTest`, `App\Models\NotificationDelivery`, `.hash`?**
  _High betweenness centrality (0.177) - this node is a cross-community bridge._
- **What connects `layouts.settings`, `layouts.settings`, `php` to the rest of the system?**
  _11 weakly-connected nodes found - possible missing edges._
- **Should `DeactivateUserActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.1 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `AccountLinkExpirationHttpTest`, `AssignProjectOwnerActionTest`, `RoleSeeder`, `UserDirectoryTest.php`, `EmailVerificationService`, `RemoveProjectMemberActionTest`, `ServiceClientAuthorizationTest`, `ReactivateUserActionTest`, `AccountLinkExpirationSettings`, `OAuthScopes`, `.members`, `TransferProjectOwnerActionTest`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `NotificationDeliveryRetryServiceTest.php`, `ProjectOwnershipTest`, `InvitationLifecycleTest`, `ProjectPolicyTest`, `ProjectOwnershipTest.php`, `RemoveProjectMemberActionTest`, `AuditLogTest`, `UserInvitationHttpTest`, `InvitationLifecycleTest.php`, `InvitationAcceptanceHttpTest`, `InvitationReissueTest`, `AuthorizationCodePkceMiddlewareTest`, `DeactivateUserActionTest.php`, `AuthorizationCodePkceTest`, `.allows`, `EmailLinkExpirationSetting`, `.recordFailure`, `PermissionMiddlewareTest`, `.retry`, `ProjectRelationshipTest`, `EmailLinkExpirationAuditTest`, `InvitationRecoveryHttpTest`, `SettingsAuthorizationTest`, `OAuthScopeIssuanceTest`, `AuthenticatedShellTest`, `NotificationDeliveryRetryTest`, `AuditLoggerTest`, `InvitationDeliveryIntegrationTest`, `UserEmailAddressFactoryTest`, `NotificationDeliveryTest`?**
  _High betweenness centrality (0.095) - this node is a cross-community bridge._
- **Should `AddProjectMemberActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.09059029807130334 - nodes in this community are weakly interconnected._
- **Why does `Role` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `AddProjectMemberActionTest`, `AccountLinkExpirationHttpTest`, `AssignProjectOwnerActionTest`, `AuditLogger`, `RoleSeeder`, `UserDirectoryTest.php`, `RemoveProjectMemberActionTest`, `Illuminate\Database\Eloquent\Model`, `ServiceClientAuthorizationTest`, `ReactivateUserActionTest`, `.members`, `TransferProjectOwnerActionTest`, `UserRoleAssignmentTest`, `NotificationDeliveryRetryServiceTest.php`, `ProjectPolicyTest`, `RemoveProjectMemberActionTest`, `InvitationLifecycleTest.php`, `DeactivateUserActionTest.php`, `.allows`, `Illuminate\Database\Eloquent\Builder`, `PermissionMiddlewareTest`, `Illuminate\Http\Request`, `.log`, `Project`, `Illuminate\Database\Seeder`, `.hash`?**
  _High betweenness centrality (0.037) - this node is a cross-community bridge._
- **Should `CredentialHasher` be split into smaller, more focused modules?**
  _Cohesion score 0.14408602150537633 - nodes in this community are weakly interconnected._
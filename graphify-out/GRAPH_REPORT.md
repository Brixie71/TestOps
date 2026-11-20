# Graph Report - TestOps  (2026-11-20)

## Corpus Check
- 345 files · ~227,482 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 28 file(s) not represented in the graph (top: (none) 20, .ini 3, .example 2)

## Summary
- 2662 nodes · 7041 edges · 219 communities (61 shown, 158 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 494 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `160f9345`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- App\Models\User
- DeactivateUserActionTest
- AddProjectMemberActionTest
- InvitationDeliveryService
- ServiceClientAuthorizationTest
- Illuminate\Foundation\Testing\RefreshDatabase
- EmailVerificationLimitSettings
- AuditLogger
- RoleSeeder
- generate.js
- UserDirectoryTest.php
- Illuminate\Http\RedirectResponse
- haraka
- EmailVerificationService
- PasswordPolicy
- RemoveProjectMemberActionTest
- Illuminate\Database\Eloquent\Model
- Tests\TestCase
- ChangeProjectMemberRoleActionTest
- App\Services\Notifications\InvitationDeliveryService
- ReactivateUserActionTest
- PasswordChangedMailable
- AccountLinkExpirationSettings
- OAuthScopes
- AssignProjectOwnerActionTest
- .members
- UserPasswordManagementTest
- Illuminate\Database\Schema\Blueprint
- PasswordResetRequest
- TransferProjectOwnerActionTest
- package.json
- web.php
- .store
- EnsureActiveAccountMiddlewareTest.php
- UserRoleAssignmentTest
- AuditLogPageTest
- NotificationPreferencesTest
- .retry
- ProjectOwnershipTest
- UserLifecycleHttpTest
- InvitationLifecycleTest
- Illuminate\Http\Request
- UserInvitation
- NotificationDeliveryHistoryTest
- ProjectPolicyTest
- ProjectOwnershipTest.php
- EmailVerificationServiceTest.php
- NotificationStatusTest
- App\Http\Controllers\Settings\BulkUserController
- NotificationDeliveryRetryServiceTest.php
- InvitationAcceptanceHttpTest
- InvitationReissueTest
- 2026_11_16_000001_create_password_reset_requests_table.php
- NotificationDelivery
- SecurityNotificationTest.php
- App\Http\Controllers\Settings\AuditLogController
- PermissionChecker
- NotificationDeliveryRetry
- PasswordPolicy
- App\Actions\AcceptInvitationAction
- AdminEmailChangeCancellationTest
- NotificationDelivery
- PermissionMiddlewareTest
- EmailChangeTest
- NotificationDeliveryRetryFactory.php
- .log
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
- {closure#1}
- InvitationDeliveryIntegrationTest
- 2026_11_05_074059_create_oauth_auth_codes_table.php
- 2026_11_05_074100_create_oauth_access_tokens_table.php
- 2026_11_05_074101_create_oauth_refresh_tokens_table.php
- 2026_11_05_074102_create_oauth_clients_table.php
- 2026_11_05_074103_create_oauth_device_codes_table.php
- 2026_11_05_102745_create_service_client_permissions_table.php
- 2026_11_09_041659_create_email_verification_requests_table.php
- 2026_11_02_051649_create_projects_table.php
- NotificationDeliveryHistoryTest.php
- 2026_11_06_062802_create_notification_delivery_retries_table.php
- UserLifecycleAuthorizer
- Illuminate\Support\Facades\Schema
- 2026_11_06_055820_create_notification_deliveries_table.php
- NotificationDeliveryTest
- EmailVerificationRequestFactory
- NotificationDeliveryHistoryController.php
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
- bootstrap/app.php
- UserDetailsPageTest
- Database\Factories\UserFactory
- layouts.settings
- BulkUserOperationsTest
- PermanentlyDeleteUserActionTest
- NotificationPreferenceEnforcementTest
- UserInvitationHttpTest
- EmailVerificationIssuanceLimitsTest
- TestSuiteTest
- AuthorizationCodePkceMiddlewareTest
- EmailVerificationLimitSettingsHttpTest
- Illuminate\Database\Eloquent\Factories\Factory
- Illuminate\Database\Eloquent\Builder
- 2026_11_04_152616_add_account_status_to_users_table.php
- 2026_11_20_134700_add_suppression_reason_to_notification_deliveries_table.php
- UserEmailAddressFactoryTest
- 2026_11_09_041659_extend_user_invitations_table_for_account_creation_module.php
- 2026_11_02_044815_create_roles_table.php
- InvitationLinkRequestFactory
- App\Services\Audit\AuditLogger
- PermanentUserDeletionHttpTest
- User
- EmailLinkExpirationSetting
- 2026_11_02_074820_create_project_user_table.php
- 2026_11_09_041659_make_users_name_nullable.php
- AuditLogTest
- 2026_11_02_045314_create_permissions_table.php
- InvitationRecoveryHttpTest
- Illuminate\Contracts\View\View
- AuthenticatedShellTest
- NotificationDeliveryRetryTest
- App\Http\Middleware\EnsureEmailLinkExpirationAccess
- AuditLoggerTest
- 2026_11_05_005052_create_system_settings_table.php
- EmailVerificationRequest
- EmailVerificationHttpTest
- 2026_11_20_135544_create_test_suites_table.php

## God Nodes (most connected - your core abstractions)
1. `User` - 213 edges
2. `TestCase` - 114 edges
3. `Role` - 89 edges
4. `Project` - 81 edges
5. `EmailVerificationService` - 81 edges
6. `AuditLogger` - 69 edges
7. `NotificationDelivery` - 43 edges
8. `ProjectMembershipRules` - 40 edges
9. `AccountLinkExpirationSettings` - 39 edges
10. `RoleSeeder` - 39 edges

## Surprising Connections (you probably didn't know these)
- `{closure#16}()` --references--> `User`  [EXTRACTED]
  tests/Feature/Settings/UserDirectoryTest.php → app/Models/User.php
- `DeactivateUserActionTest` --references--> `DeactivateUserAction`  [EXTRACTED]
  tests/Feature/Actions/DeactivateUserActionTest.php → app/Actions/DeactivateUserAction.php
- `EmailVerificationHttpTest` --references--> `EmailVerificationService`  [EXTRACTED]
  tests/Feature/Auth/EmailVerificationHttpTest.php → app/Services/Auth/EmailVerificationService.php
- `EmailVerificationIssuanceLimitsTest` --references--> `EmailVerificationService`  [EXTRACTED]
  tests/Feature/Auth/EmailVerificationIssuanceLimitsTest.php → app/Services/Auth/EmailVerificationService.php
- `PermanentlyDeleteUserActionTest` --references--> `PermanentlyDeleteUserAction`  [EXTRACTED]
  tests/Feature/Actions/PermanentlyDeleteUserActionTest.php → app/Actions/PermanentlyDeleteUserAction.php

## Import Cycles
- None detected.

## Communities (219 total, 158 thin omitted)

### Community 0 - "App\Models\User"
Cohesion: 0.12
Nodes (4): ChangeOwnPasswordAction, {closure#1}(), AccountSecurityNotificationService, NotificationPreferenceChecker

### Community 1 - "DeactivateUserActionTest"
Cohesion: 0.09
Nodes (4): {closure#3}(), RevokesUserCredentials, DeactivateUserActionTest, RevokesUserCredentialsTest

### Community 2 - "AddProjectMemberActionTest"
Cohesion: 0.09
Nodes (3): AddProjectMemberActionTest, EmailLinkExpirationAuthorizationTest, ProjectPolicyAbilitiesTest

### Community 3 - "InvitationDeliveryService"
Cohesion: 0.16
Nodes (13): AcceptInvitationAction, CancelUserInvitationAction, IssueUserInvitationAction, RecoverUserInvitationAction, ResendUserInvitationAction, InvitationRecoveryController, Controller, UserInvitationController (+5 more)

### Community 4 - "ServiceClientAuthorizationTest"
Cohesion: 0.06
Nodes (4): AuthorizationCodePkceTest, OAuthScopeIssuanceTest, ServiceClientAuthorizationTest, AccountLinkExpirationHttpTest

### Community 5 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.08
Nodes (7): AuditLog, Permission, Role, OAuthScopes, ExampleTest, {closure#1}(), TestCase

### Community 6 - "EmailVerificationLimitSettings"
Cohesion: 0.26
Nodes (3): SystemSettingEmailVerificationLimitController, EmailVerificationLimitSettings, {closure#1}()

### Community 7 - "AuditLogger"
Cohesion: 0.12
Nodes (19): AddProjectMemberAction, {closure#1}(), AssignProjectOwnerAction, {closure#1}(), ChangeProjectAutoAssignmentSettingAction, {closure#1}(), ChangeProjectMemberRoleAction, {closure#1}() (+11 more)

### Community 8 - "RoleSeeder"
Cohesion: 0.11
Nodes (6): ProjectAuthorizationPermissionSeeder, ProjectRoleSeeder, RoleSeeder, ProjectAuthorizationPermissionSeederTest, ProjectRoleSeederTest, RoleSeederTest

### Community 9 - "generate.js"
Cohesion: 0.06
Nodes (23): allNoBorders, ASSETS, body, BODY_FONT, bodyMargin, buildCoverR1(), bullet(), calcCoverSpacing() (+15 more)

### Community 11 - "Illuminate\Http\RedirectResponse"
Cohesion: 0.08
Nodes (8): CancelEmailChangeAction, {closure#2}(), {closure#1}(), UpdateUserProfileAction, ProfileController, UserDeletionController, UserEmailChangeController, UserProfileController

### Community 12 - "haraka"
Cohesion: 0.06
Nodes (31): context, dockerfile, driver, DELIVERY_MODE, HARAKA_SMTP_PASSWORD, HARAKA_SMTP_USER, MP_DATABASE, MP_MAX_MESSAGES (+23 more)

### Community 15 - "RemoveProjectMemberActionTest"
Cohesion: 0.09
Nodes (3): ChangeAutoAssignmentSettingActionTest, RemoveProjectMemberActionTest, UnassignProjectOwnerActionTest

### Community 16 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.08
Nodes (6): EmailVerificationAttemptCounter, {closure#1}(), PasswordResetRequest, ServiceClientPermission, TestSuite, UserNotificationPreference

### Community 17 - "Tests\TestCase"
Cohesion: 0.07
Nodes (3): {closure#1}(), {closure#1}(), ProfileUpdateTest

### Community 19 - "App\Services\Notifications\InvitationDeliveryService"
Cohesion: 0.15
Nodes (5): DirectAccountSetupMailable, EmailChangeCancelledMailable, EmailChangeVerificationMailable, EmailVerificationMailable, PasswordResetMailable

### Community 21 - "PasswordChangedMailable"
Cohesion: 0.11
Nodes (3): PasswordChangedMailable, {closure#1}(), PasswordChangeTest

### Community 22 - "AccountLinkExpirationSettings"
Cohesion: 0.20
Nodes (3): SystemSettingAccountLinkExpirationController, AccountLinkExpirationSettings, AccountLinkExpirationSettingsTest

### Community 23 - "OAuthScopes"
Cohesion: 0.10
Nodes (4): AppServiceProvider, {closure#1}(), ScopeRepository, PassportConfigurationTest

### Community 25 - ".members"
Cohesion: 0.05
Nodes (4): ChangeProjectOwnerAssignmentTypeActionTest, ProjectMembershipHttpTest, ProjectOwnershipHttpTest, ProjectReadHttpTest

### Community 27 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.10
Nodes (13): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}() (+5 more)

### Community 31 - "package.json"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 40 - "UserLifecycleHttpTest"
Cohesion: 0.10
Nodes (3): UserManagementPermissionSeeder, AdminProfileUpdateTest, UserLifecycleHttpTest

### Community 42 - "Illuminate\Http\Request"
Cohesion: 0.16
Nodes (4): AuthenticatedSessionController, SystemSettingAccountLinkExpirationResource, SystemSettingEmailLinkExpirationResource, SystemSettingEmailVerificationLimitResource

### Community 43 - "UserInvitation"
Cohesion: 0.07
Nodes (6): {closure#1}(), {closure#1}(), {closure#1}(), InvitationLinkRequest, UserInvitation, InvitationRateLimitException

### Community 49 - "App\Http\Controllers\Settings\BulkUserController"
Cohesion: 0.22
Nodes (4): AssignUserRoleAction, BulkUserController, {closure#2}(), UserRoleController

### Community 53 - "2026_11_16_000001_create_password_reset_requests_table.php"
Cohesion: 0.33
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), createOutstandingRequestUniqueIndex(), up()

### Community 54 - "NotificationDelivery"
Cohesion: 0.21
Nodes (5): NotificationDelivery, {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}()

### Community 56 - "SecurityNotificationTest.php"
Cohesion: 0.11
Nodes (8): AccountDeactivatedMailable, AccountReactivatedMailable, SystemRoleChangedMailable, SecurityNotificationRetryService, {closure#1}(), {closure#2}(), {closure#3}(), SecurityNotificationTest

### Community 57 - "App\Http\Controllers\Settings\AuditLogController"
Cohesion: 0.14
Nodes (7): {closure#10}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}()

### Community 58 - "PermissionChecker"
Cohesion: 0.15
Nodes (6): ProjectController, {closure#1}(), EnforceAuthorizationCodePkce, EnsurePermission, EnsureUserIsSuperAdmin, PermissionChecker

### Community 59 - "NotificationDeliveryRetry"
Cohesion: 0.16
Nodes (4): NotificationDeliveryHistoryController, NotificationDeliveryRetry, {closure#2}(), NotificationDeliveryRetryService

### Community 60 - "PasswordPolicy"
Cohesion: 0.20
Nodes (4): CompleteForcedPasswordResetAction, ForcedPasswordResetController, PasswordPolicy, PasswordResetService

### Community 63 - "NotificationDelivery"
Cohesion: 0.16
Nodes (3): NotificationDeliveryRecorder, {closure#1}(), NotificationDeliveryRecorderTest

### Community 67 - ".log"
Cohesion: 0.15
Nodes (9): {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#2}(), {closure#1}() (+1 more)

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
Cohesion: 0.09
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

### Community 104 - "NotificationDeliveryHistoryTest.php"
Cohesion: 0.40
Nodes (3): {closure#2}(), {closure#3}(), {closure#4}()

### Community 106 - "UserLifecycleAuthorizer"
Cohesion: 0.12
Nodes (6): {closure#1}(), {closure#1}(), {closure#2}(), UserDirectoryController, UserLifecycleAuthorizer, PermissionCheckerTest

### Community 107 - "Illuminate\Support\Facades\Schema"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 112 - "NotificationDeliveryHistoryController.php"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 114 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 115 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 127 - "bootstrap/app.php"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 174 - "PermanentlyDeleteUserActionTest"
Cohesion: 0.17
Nodes (3): LifecycleConnectionGuard, PermanentlyDeleteUserActionTest, LifecycleConnectionGuardTest

### Community 187 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.15
Nodes (16): {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+8 more)

### Community 194 - "App\Services\Audit\AuditLogger"
Cohesion: 0.17
Nodes (8): DeactivateUserAction, ForceUserPasswordResetAction, PermanentlyDeleteUserAction, ReactivateUserAction, SetUserPasswordAction, UserLifecycleController, UserPasswordController, UserDeletionDependencyInspector

### Community 196 - "User"
Cohesion: 0.13
Nodes (3): Project, User, ProjectPolicy

### Community 197 - "EmailLinkExpirationSetting"
Cohesion: 0.16
Nodes (5): SystemSettingEmailLinkExpirationController, SystemSetting, AuditLogger, EmailLinkExpirationSetting, EmailLinkExpirationSettingTest

### Community 203 - "InvitationRecoveryHttpTest"
Cohesion: 0.23
Nodes (4): InvitationLinkMailable, {closure#1}(), InvitationRecoveryHttpTest, {closure#1}()

### Community 205 - "Illuminate\Contracts\View\View"
Cohesion: 0.14
Nodes (7): {closure#1}(), {closure#1}(), {closure#2}(), EmailChangeVerificationController, EmailVerificationController, InvitationAcceptanceController, UserEmailAddress

### Community 215 - "EmailVerificationRequest"
Cohesion: 0.13
Nodes (4): {closure#2}(), RequestEmailChangeAction, EmailVerificationRequest, EmailVerificationRateLimitException

## Knowledge Gaps
- **10 isolated node(s):** `php`, `layouts.app`, `layouts.app`, `layouts.app`, `layouts.app` (+5 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 677 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **158 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `App\Models\User`, `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `InvitationDeliveryService`, `ServiceClientAuthorizationTest`, `Illuminate\Foundation\Testing\RefreshDatabase`, `AuditLogger`, `UserDirectoryTest.php`, `Illuminate\Http\RedirectResponse`, `RemoveProjectMemberActionTest`, `Illuminate\Database\Eloquent\Model`, `Tests\TestCase`, `ChangeProjectMemberRoleActionTest`, `ReactivateUserActionTest`, `AccountLinkExpirationSettings`, `AssignProjectOwnerActionTest`, `.members`, `Illuminate\Support\Facades\DB`, `TransferProjectOwnerActionTest`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `.retry`, `UserDetailsPageTest`, `UserInvitation`, `Database\Factories\UserFactory`, `ProjectPolicyTest`, `ProjectOwnershipTest.php`, `EmailVerificationServiceTest.php`, `App\Http\Controllers\Settings\BulkUserController`, `NotificationDeliveryRetryServiceTest.php`, `InvitationAcceptanceHttpTest`, `InvitationReissueTest`, `UserInvitationHttpTest`, `PermissionChecker`, `Illuminate\Database\Eloquent\Builder`, `NotificationDeliveryRetry`, `App\Actions\AcceptInvitationAction`, `UserEmailAddressFactoryTest`, `App\Services\Audit\AuditLogger`, `.log`, `ProjectFactory.php`, `EmailLinkExpirationSetting`, `EmailLinkExpirationAuditTest`, `InvitationRecoveryHttpTest`, `Illuminate\Contracts\View\View`, `Illuminate\Database\Seeder`, `SettingsAuthorizationTest`, `AuthenticatedShellTest`, `App\Http\Middleware\EnsureEmailLinkExpirationAccess`, `EmailVerificationHttpTest`, `Illuminate\Database\Eloquent\Relations\HasMany`, `InvitationDeliveryIntegrationTest`, `UserLifecycleAuthorizer`?**
  _High betweenness centrality (0.209) - this node is a cross-community bridge._
- **What connects `php`, `layouts.app`, `layouts.app` to the rest of the system?**
  _10 weakly-connected nodes found - possible missing edges._
- **Should `App\Models\User` be split into smaller, more focused modules?**
  _Cohesion score 0.11904761904761904 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `InvitationDeliveryService`, `ServiceClientAuthorizationTest`, `RoleSeeder`, `UserDirectoryTest.php`, `EmailVerificationService`, `RemoveProjectMemberActionTest`, `Tests\TestCase`, `ChangeProjectMemberRoleActionTest`, `ReactivateUserActionTest`, `AccountLinkExpirationSettings`, `OAuthScopes`, `AssignProjectOwnerActionTest`, `.members`, `Illuminate\Support\Facades\DB`, `TransferProjectOwnerActionTest`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `.retry`, `ProjectOwnershipTest`, `InvitationLifecycleTest`, `UserDetailsPageTest`, `ProjectPolicyTest`, `ProjectOwnershipTest.php`, `EmailVerificationServiceTest.php`, `PermanentlyDeleteUserActionTest`, `UserInvitationHttpTest`, `NotificationDeliveryRetryServiceTest.php`, `InvitationAcceptanceHttpTest`, `InvitationReissueTest`, `AuthorizationCodePkceMiddlewareTest`, `UserEmailAddressFactoryTest`, `NotificationDelivery`, `PermissionMiddlewareTest`, `EmailLinkExpirationSetting`, `AuditLogTest`, `ProjectRelationshipTest`, `EmailLinkExpirationAuditTest`, `InvitationRecoveryHttpTest`, `SettingsAuthorizationTest`, `AuthenticatedShellTest`, `NotificationDeliveryRetryTest`, `AuditLoggerTest`, `EmailVerificationHttpTest`, `InvitationDeliveryIntegrationTest`, `UserLifecycleAuthorizer`, `NotificationDeliveryTest`?**
  _High betweenness centrality (0.093) - this node is a cross-community bridge._
- **Should `DeactivateUserActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.09437386569872959 - nodes in this community are weakly interconnected._
- **Why does `Role` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `AddProjectMemberActionTest`, `InvitationDeliveryService`, `ServiceClientAuthorizationTest`, `AuditLogger`, `RoleSeeder`, `UserDirectoryTest.php`, `RemoveProjectMemberActionTest`, `Illuminate\Database\Eloquent\Model`, `Tests\TestCase`, `ChangeProjectMemberRoleActionTest`, `ReactivateUserActionTest`, `AssignProjectOwnerActionTest`, `.members`, `Illuminate\Support\Facades\DB`, `TransferProjectOwnerActionTest`, `UserRoleAssignmentTest`, `UserDetailsPageTest`, `ProjectPolicyTest`, `App\Http\Controllers\Settings\BulkUserController`, `NotificationDeliveryRetryServiceTest.php`, `Illuminate\Database\Eloquent\Builder`, `PermissionMiddlewareTest`, `User`, `Illuminate\Database\Seeder`, `Illuminate\Database\Eloquent\Relations\HasMany`, `UserLifecycleAuthorizer`?**
  _High betweenness centrality (0.023) - this node is a cross-community bridge._
- **Should `AddProjectMemberActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.09059029807130334 - nodes in this community are weakly interconnected._
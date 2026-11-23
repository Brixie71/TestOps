# Graph Report - TestOps  (2026-11-23)

## Corpus Check
- 357 files · ~234,739 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 28 file(s) not represented in the graph (top: (none) 20, .ini 3, .example 2)

## Summary
- 2775 nodes · 7342 edges · 238 communities (57 shown, 181 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 511 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `1a62dc35`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Illuminate\Support\Facades\DB
- DeactivateUserActionTest
- AddProjectMemberActionTest
- CredentialHasher
- AccountLinkExpirationHttpTest
- Illuminate\Foundation\Testing\RefreshDatabase
- RemoveProjectMemberActionTest
- ProjectMembershipRules
- RoleSeeder
- generate.js
- UserDirectoryTest.php
- UpdateUserProfileAction
- haraka
- EmailVerificationService
- AssignProjectOwnerActionTest
- TransferProjectOwnerActionTest
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
- UserLifecycleAuthorizer
- ChangeProjectMemberRoleActionTest
- package.json
- web.php
- PermissionChecker
- EnsureActiveAccountMiddlewareTest.php
- UserRoleAssignmentTest
- AuditLogPageTest
- NotificationPreferencesTest
- .retry
- ProjectOwnershipTest
- UserLifecycleHttpTest
- InvitationLifecycleTest
- User
- UserInvitation
- NotificationDeliveryHistoryTest
- ProjectPolicyTest
- SystemAnnouncementMailable
- AuditLogController.php
- AddProjectMemberAction
- App\Services\Audit\AuditLogger
- NotificationDeliveryRetryServiceTest.php
- InvitationAcceptanceHttpTest
- InvitationReissueTest
- 2026_11_16_000001_create_password_reset_requests_table.php
- App\Http\Controllers\Settings\NotificationDeliveryHistoryController
- App\Models\User
- AccountReactivatedMailable
- Closure
- App\Services\Authorization\PermissionChecker
- EmailLinkExpirationSetting
- PasswordPolicy
- App\Actions\AcceptInvitationAction
- AdminEmailChangeCancellationTest
- App\Models\NotificationDelivery
- PermissionMiddlewareTest
- EmailChangeTest
- NotificationDeliveryRetryFactory.php
- .log
- ProjectFactory.php
- UserFactory
- EmailChangeVerificationTest
- NotificationDeliveryRetry
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
- PasswordPolicyTest
- InvitationDeliveryIntegrationTest
- 2026_11_05_074059_create_oauth_auth_codes_table.php
- 2026_11_05_074100_create_oauth_access_tokens_table.php
- 2026_11_05_074101_create_oauth_refresh_tokens_table.php
- 2026_11_05_074102_create_oauth_clients_table.php
- 2026_11_05_074103_create_oauth_device_codes_table.php
- 2026_11_05_102745_create_service_client_permissions_table.php
- 2026_11_09_041659_create_email_verification_requests_table.php
- 2026_11_02_051649_create_projects_table.php
- {closure#1}
- 2026_11_06_062802_create_notification_delivery_retries_table.php
- AuditLogController
- Illuminate\Support\Facades\Schema
- 2026_11_06_055820_create_notification_deliveries_table.php
- NotificationDeliveryTest
- EmailVerificationRequestFactory
- Illuminate\Database\Eloquent\Relations\BelongsTo
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
- NotificationDelivery
- layouts.settings
- BulkUserOperationsTest
- PermanentlyDeleteUserActionTest
- EmailVerificationLimitSettings
- UserInvitationHttpTest
- AdminProfileUpdateTest
- InvitationDeliveryService
- AuthorizationCodePkceMiddlewareTest
- PasswordResetException
- AuthorizationCodePkceTest
- Illuminate\Database\Eloquent\Builder
- 2026_11_04_152616_add_account_status_to_users_table.php
- 2026_11_20_134700_add_suppression_reason_to_notification_deliveries_table.php
- PasswordChangeTest
- 2026_11_09_041659_extend_user_invitations_table_for_account_creation_module.php
- 2026_11_02_044815_create_roles_table.php
- UserNotificationPreference
- PermissionManagementTest
- PermanentUserDeletionHttpTest
- .view
- CancelEmailChangeAction
- 2026_11_02_074820_create_project_user_table.php
- 2026_11_09_041659_make_users_name_nullable.php
- ProfileUpdateTest
- 2026_11_02_045314_create_permissions_table.php
- InvitationRecoveryHttpTest
- NotificationDeliveryRetryTest
- SystemAnnouncementNotificationService
- OAuthScopeIssuanceTest
- Illuminate\Http\Request
- 2026_11_05_005052_create_system_settings_table.php
- AuthenticatedShellTest
- ServiceClientPermissionTest
- 2026_11_20_135544_create_test_suites_table.php
- AuditLoggerTest
- TestSuiteTest
- NotificationPreferenceEnforcementTest
- RequestEmailChangeAction
- audit-logs.blade.php
- user-details.blade.php
- EmailVerificationAttemptCounter
- notifications.blade.php
- users.blade.php
- AuditLogTest
- UserEmailAddressFactoryTest
- ExampleTest
- roles.blade.php

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
- `{closure#2}()` --references--> `AccountReactivatedMailable`  [EXTRACTED]
  tests/Feature/Actions/SecurityNotificationTest.php → app/Mail/AccountReactivatedMailable.php
- `{closure#16}()` --references--> `User`  [EXTRACTED]
  tests/Feature/Settings/UserDirectoryTest.php → app/Models/User.php
- `DeactivateUserActionTest` --references--> `DeactivateUserAction`  [EXTRACTED]
  tests/Feature/Actions/DeactivateUserActionTest.php → app/Actions/DeactivateUserAction.php
- `PermanentlyDeleteUserActionTest` --references--> `PermanentlyDeleteUserAction`  [EXTRACTED]
  tests/Feature/Actions/PermanentlyDeleteUserActionTest.php → app/Actions/PermanentlyDeleteUserAction.php
- `{closure#1}()` --references--> `InvitationLinkMailable`  [EXTRACTED]
  tests/Feature/Auth/InvitationRecoveryHttpTest.php → app/Mail/InvitationLinkMailable.php

## Import Cycles
- None detected.

## Communities (238 total, 181 thin omitted)

### Community 2 - "AddProjectMemberActionTest"
Cohesion: 0.09
Nodes (3): AddProjectMemberActionTest, EmailLinkExpirationAuthorizationTest, ProjectPolicyAbilitiesTest

### Community 3 - "CredentialHasher"
Cohesion: 0.11
Nodes (7): IssueUserInvitationAction, InvitationLinkRequest, CredentialHasher, InvitationCredentialGenerator, InvitationLinkRequestIssuer, InvitationRateLimiter, InvitationRateLimitException

### Community 5 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.08
Nodes (5): AuditLog, Permission, Role, {closure#1}(), TestCase

### Community 7 - "ProjectMembershipRules"
Cohesion: 0.18
Nodes (7): AssignProjectOwnerAction, ChangeProjectAutoAssignmentSettingAction, ChangeProjectOwnerAssignmentTypeAction, TransferProjectOwnerAction, UnassignProjectOwnerAction, ProjectOwnershipController, ProjectMembershipRules

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
Cohesion: 0.05
Nodes (18): {closure#1}(), {closure#1}(), CompleteAccountActivationAction, {closure#1}(), {closure#2}(), CompleteEmailChangeAction, {closure#1}(), {closure#2}() (+10 more)

### Community 15 - "TransferProjectOwnerActionTest"
Cohesion: 0.10
Nodes (3): ChangeAutoAssignmentSettingActionTest, TransferProjectOwnerActionTest, UnassignProjectOwnerActionTest

### Community 16 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.20
Nodes (3): {closure#1}(), PasswordResetRequest, TestSuite

### Community 19 - "Illuminate\Mail\Mailable"
Cohesion: 0.14
Nodes (11): AccountReactivatedMailable, DirectAccountSetupMailable, EmailChangeCancelledMailable, EmailVerificationMailable, PasswordChangedMailable, PasswordResetMailable, SecurityNotificationRetryService, {closure#1}() (+3 more)

### Community 22 - "AccountLinkExpirationSettings"
Cohesion: 0.15
Nodes (5): {closure#1}(), {closure#2}(), SystemSettingAccountLinkExpirationController, AccountLinkExpirationSettings, AccountLinkExpirationSettingsTest

### Community 23 - "OAuthScopes"
Cohesion: 0.10
Nodes (4): AppServiceProvider, {closure#1}(), ScopeRepository, PassportConfigurationTest

### Community 25 - ".members"
Cohesion: 0.05
Nodes (4): ChangeProjectOwnerAssignmentTypeActionTest, ProjectMembershipHttpTest, ProjectOwnershipHttpTest, ProjectReadHttpTest

### Community 27 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.10
Nodes (13): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}() (+5 more)

### Community 29 - "UserLifecycleAuthorizer"
Cohesion: 0.12
Nodes (10): {closure#1}(), {closure#1}(), {closure#1}(), ForceUserPasswordResetAction, {closure#1}(), {closure#2}(), SetUserPasswordAction, {closure#1}() (+2 more)

### Community 31 - "package.json"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 33 - "PermissionChecker"
Cohesion: 0.16
Nodes (5): {closure#1}(), EnforceAuthorizationCodePkce, EnsurePermission, EnsureUserIsSuperAdmin, PermissionChecker

### Community 43 - "UserInvitation"
Cohesion: 0.17
Nodes (3): {closure#1}(), {closure#1}(), UserInvitation

### Community 46 - "SystemAnnouncementMailable"
Cohesion: 0.18
Nodes (5): SystemAnnouncementMailable, {closure#1}(), {closure#2}(), {closure#3}(), SystemAnnouncementTest

### Community 47 - "AuditLogController.php"
Cohesion: 0.09
Nodes (13): {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}(), {closure#17}(), {closure#4}() (+5 more)

### Community 48 - "AddProjectMemberAction"
Cohesion: 0.22
Nodes (4): AddProjectMemberAction, ChangeProjectMemberRoleAction, RemoveProjectMemberAction, ProjectMembershipController

### Community 49 - "App\Services\Audit\AuditLogger"
Cohesion: 0.15
Nodes (8): AssignUserRoleAction, ChangeOwnPasswordAction, DeactivateUserAction, ReactivateUserAction, BulkUserController, {closure#2}(), {closure#3}(), AccountSecurityNotificationService

### Community 53 - "2026_11_16_000001_create_password_reset_requests_table.php"
Cohesion: 0.33
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), createOutstandingRequestUniqueIndex(), up()

### Community 54 - "App\Http\Controllers\Settings\NotificationDeliveryHistoryController"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 55 - "App\Models\User"
Cohesion: 0.11
Nodes (4): RevokesUserCredentials, {closure#1}(), {closure#1}(), {closure#1}()

### Community 56 - "AccountReactivatedMailable"
Cohesion: 0.15
Nodes (6): AccountDeactivatedMailable, SystemRoleChangedMailable, {closure#1}(), {closure#2}(), {closure#3}(), SecurityNotificationTest

### Community 57 - "Closure"
Cohesion: 0.16
Nodes (3): EnsureEmailLinkExpirationAccess, ServiceClientAuthorizer, {closure#1}()

### Community 58 - "App\Services\Authorization\PermissionChecker"
Cohesion: 0.16
Nodes (5): {closure#1}(), PermanentlyDeleteUserAction, UserDeletionController, UserDirectoryController, UserDeletionDependencyInspector

### Community 59 - "EmailLinkExpirationSetting"
Cohesion: 0.18
Nodes (5): SystemSettingEmailLinkExpirationController, SystemSetting, AuditLogger, EmailLinkExpirationSetting, EmailLinkExpirationSettingTest

### Community 60 - "PasswordPolicy"
Cohesion: 0.17
Nodes (4): CompleteForcedPasswordResetAction, ForcedPasswordResetController, PasswordPolicy, PasswordResetService

### Community 63 - "App\Models\NotificationDelivery"
Cohesion: 0.15
Nodes (4): NotificationDeliveryRecorder, {closure#1}(), {closure#1}(), NotificationDeliveryRecorderTest

### Community 65 - "EmailChangeTest"
Cohesion: 0.14
Nodes (3): EmailChangeVerificationMailable, {closure#1}(), EmailChangeTest

### Community 67 - ".log"
Cohesion: 0.17
Nodes (12): {closure#1}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}() (+4 more)

### Community 71 - "NotificationDeliveryRetry"
Cohesion: 0.19
Nodes (4): NotificationDeliveryHistoryController, NotificationDeliveryRetry, {closure#2}(), NotificationDeliveryRetryService

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
Cohesion: 0.08
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

### Community 171 - "NotificationDelivery"
Cohesion: 0.23
Nodes (5): NotificationDelivery, {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}()

### Community 174 - "PermanentlyDeleteUserActionTest"
Cohesion: 0.17
Nodes (3): LifecycleConnectionGuard, PermanentlyDeleteUserActionTest, LifecycleConnectionGuardTest

### Community 179 - "InvitationDeliveryService"
Cohesion: 0.25
Nodes (7): AcceptInvitationAction, CancelUserInvitationAction, RecoverUserInvitationAction, ResendUserInvitationAction, InvitationRecoveryController, UserInvitationController, InvitationDeliveryService

### Community 183 - "PasswordResetException"
Cohesion: 0.11
Nodes (4): {closure#2}(), PasswordResetException, NotificationStatusTest, EmailVerificationLimitSettingsHttpTest

### Community 187 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.14
Nodes (17): {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+9 more)

### Community 203 - "InvitationRecoveryHttpTest"
Cohesion: 0.26
Nodes (4): InvitationLinkMailable, {closure#1}(), InvitationRecoveryHttpTest, {closure#1}()

### Community 211 - "Illuminate\Http\Request"
Cohesion: 0.07
Nodes (14): InvitationAcceptanceController, AuthenticatedSessionController, Controller, PermissionController, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}() (+6 more)

## Knowledge Gaps
- **15 isolated node(s):** `layouts.settings`, `layouts.settings`, `php`, `layouts.app`, `layouts.app` (+10 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 696 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **181 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `Illuminate\Support\Facades\DB`, `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `CredentialHasher`, `AccountLinkExpirationHttpTest`, `Illuminate\Foundation\Testing\RefreshDatabase`, `RemoveProjectMemberActionTest`, `ProjectMembershipRules`, `RoleSeeder`, `UserDirectoryTest.php`, `EmailVerificationService`, `AssignProjectOwnerActionTest`, `TransferProjectOwnerActionTest`, `Illuminate\Database\Eloquent\Model`, `ServiceClientAuthorizationTest`, `ReactivateUserActionTest`, `AccountLinkExpirationSettings`, `PermissionChecker`, `.members`, `ChangeProjectMemberRoleActionTest`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `.retry`, `UserInvitation`, `ProjectPolicyTest`, `AddProjectMemberAction`, `App\Services\Audit\AuditLogger`, `NotificationDeliveryRetryServiceTest.php`, `InvitationAcceptanceHttpTest`, `InvitationDeliveryService`, `InvitationReissueTest`, `UserInvitationHttpTest`, `App\Models\User`, `Closure`, `App\Services\Authorization\PermissionChecker`, `Illuminate\Database\Eloquent\Builder`, `EmailLinkExpirationSetting`, `App\Actions\AcceptInvitationAction`, `AuthorizationCodePkceTest`, `UserNotificationPreference`, `.log`, `.view`, `ProjectFactory.php`, `UserFactory`, `NotificationDeliveryRetry`, `EmailLinkExpirationAuditTest`, `Illuminate\Database\Seeder`, `SettingsAuthorizationTest`, `Illuminate\Http\Request`, `InvitationDeliveryIntegrationTest`, `{closure#1}`?**
  _High betweenness centrality (0.191) - this node is a cross-community bridge._
- **What connects `layouts.settings`, `layouts.settings`, `php` to the rest of the system?**
  _15 weakly-connected nodes found - possible missing edges._
- **Should `DeactivateUserActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.1085972850678733 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `CredentialHasher`, `AccountLinkExpirationHttpTest`, `RemoveProjectMemberActionTest`, `RoleSeeder`, `UserDirectoryTest.php`, `EmailVerificationService`, `AssignProjectOwnerActionTest`, `TransferProjectOwnerActionTest`, `ServiceClientAuthorizationTest`, `ReactivateUserActionTest`, `AccountLinkExpirationSettings`, `OAuthScopes`, `PermissionChecker`, `.members`, `ChangeProjectMemberRoleActionTest`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `.retry`, `ProjectOwnershipTest`, `InvitationLifecycleTest`, `User`, `ProjectPolicyTest`, `PermanentlyDeleteUserActionTest`, `UserInvitationHttpTest`, `NotificationDeliveryRetryServiceTest.php`, `InvitationAcceptanceHttpTest`, `InvitationDeliveryService`, `InvitationReissueTest`, `AuthorizationCodePkceMiddlewareTest`, `App\Models\User`, `AuthorizationCodePkceTest`, `EmailLinkExpirationSetting`, `App\Models\NotificationDelivery`, `PermissionMiddlewareTest`, `ProjectRelationshipTest`, `EmailLinkExpirationAuditTest`, `InvitationRecoveryHttpTest`, `NotificationDeliveryRetryTest`, `SettingsAuthorizationTest`, `OAuthScopeIssuanceTest`, `AuthenticatedShellTest`, `ServiceClientPermissionTest`, `AuditLoggerTest`, `InvitationDeliveryIntegrationTest`, `AuditLogTest`, `UserEmailAddressFactoryTest`, `ExampleTest`, `NotificationDeliveryTest`?**
  _High betweenness centrality (0.086) - this node is a cross-community bridge._
- **Should `AddProjectMemberActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.09059029807130334 - nodes in this community are weakly interconnected._
- **Why does `ProjectOwnershipTest` connect `ProjectOwnershipTest` to `User`, `Illuminate\Foundation\Testing\RefreshDatabase`?**
  _High betweenness centrality (0.024) - this node is a cross-community bridge._
- **Should `CredentialHasher` be split into smaller, more focused modules?**
  _Cohesion score 0.10960960960960961 - nodes in this community are weakly interconnected._
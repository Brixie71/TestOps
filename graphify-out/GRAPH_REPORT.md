# Graph Report - TestOps  (2026-11-23)

## Corpus Check
- 356 files · ~233,151 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 28 file(s) not represented in the graph (top: (none) 20, .ini 3, .example 2)

## Summary
- 2749 nodes · 7271 edges · 229 communities (59 shown, 170 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 506 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `2c2fa133`
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
- AuditLogger
- RoleSeeder
- generate.js
- UserDirectoryTest.php
- UpdateUserProfileAction
- haraka
- EmailVerificationService
- InvitationLinkRequest
- AssignProjectOwnerActionTest
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
- ProjectReadHttpTest
- package.json
- web.php
- EnsureActiveAccountMiddlewareTest.php
- UserRoleAssignmentTest
- AuditLogPageTest
- Tests\TestCase
- NotificationDeliveryRetryServiceTest.php
- ProjectOwnershipTest
- UserLifecycleHttpTest
- InvitationLifecycleTest
- .hash
- UserInvitation
- NotificationDeliveryHistoryTest
- ProjectPolicyTest
- SystemAnnouncementMailable
- AuditLogController.php
- AdminEmailChangeCancellationTest.php
- App\Services\Audit\AuditLogger
- .consume
- InvitationAcceptanceHttpTest
- InvitationReissueTest
- 2026_11_16_000001_create_password_reset_requests_table.php
- App\Http\Controllers\Settings\NotificationDeliveryHistoryController
- App\Models\Role
- AccountReactivatedMailable
- Closure
- App\Services\Authorization\PermissionChecker
- EmailLinkExpirationSetting
- App\Services\Audit\AuditLogger
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
- EmailVerificationRequest
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
- EmailVerificationIssuanceLimitsTest
- InvitationDeliveryIntegrationTest
- 2026_11_05_074059_create_oauth_auth_codes_table.php
- 2026_11_05_074100_create_oauth_access_tokens_table.php
- 2026_11_05_074101_create_oauth_refresh_tokens_table.php
- 2026_11_05_074102_create_oauth_clients_table.php
- 2026_11_05_074103_create_oauth_device_codes_table.php
- 2026_11_05_102745_create_service_client_permissions_table.php
- 2026_11_09_041659_create_email_verification_requests_table.php
- 2026_11_02_051649_create_projects_table.php
- .log
- 2026_11_06_062802_create_notification_delivery_retries_table.php
- AuditLogController
- Illuminate\Support\Facades\Schema
- 2026_11_06_055820_create_notification_deliveries_table.php
- NotificationDeliveryTest
- EmailVerificationRequestFactory
- Laravel\Passport\Client
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
- App\Http\Controllers\Controller
- UserInvitationHttpTest
- AdminProfileUpdateTest
- InvitationLifecycleTest.php
- AuthorizationCodePkceMiddlewareTest
- EmailVerificationLimitSettingsHttpTest
- AuthorizationCodePkceTest
- Illuminate\Database\Eloquent\Builder
- 2026_11_04_152616_add_account_status_to_users_table.php
- 2026_11_20_134700_add_suppression_reason_to_notification_deliveries_table.php
- PasswordChangeTest
- 2026_11_09_041659_extend_user_invitations_table_for_account_creation_module.php
- 2026_11_02_044815_create_roles_table.php
- .store
- ForceUserPasswordResetAction
- PermanentUserDeletionHttpTest
- .view
- EmailVerificationHttpTest
- 2026_11_02_074820_create_project_user_table.php
- 2026_11_09_041659_make_users_name_nullable.php
- ProfileUpdateTest
- 2026_11_02_045314_create_permissions_table.php
- InvitationRecoveryHttpTest
- App\Services\Auth\CredentialHasher
- SystemAnnouncementNotificationService
- OAuthScopeIssuanceTest
- Illuminate\Http\Request
- 2026_11_05_005052_create_system_settings_table.php
- AuthenticatedShellTest
- 2026_11_20_135544_create_test_suites_table.php
- AuditLoggerTest
- PassportConfigurationTest
- audit-logs.blade.php
- user-details.blade.php
- notifications.blade.php
- users.blade.php

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
- `{closure#1}()` --references--> `InvitationLinkMailable`  [EXTRACTED]
  tests/Feature/Auth/InvitationRecoveryHttpTest.php → app/Mail/InvitationLinkMailable.php
- `{closure#1}()` --references--> `InvitationLinkMailable`  [EXTRACTED]
  tests/Feature/Settings/UserInvitationHttpTest.php → app/Mail/InvitationLinkMailable.php
- `{closure#2}()` --references--> `AccountReactivatedMailable`  [EXTRACTED]
  tests/Feature/Actions/SecurityNotificationTest.php → app/Mail/AccountReactivatedMailable.php

## Import Cycles
- None detected.

## Communities (229 total, 170 thin omitted)

### Community 1 - "DeactivateUserActionTest"
Cohesion: 0.10
Nodes (4): {closure#3}(), RevokesUserCredentials, DeactivateUserActionTest, RevokesUserCredentialsTest

### Community 2 - "AddProjectMemberActionTest"
Cohesion: 0.09
Nodes (3): AddProjectMemberActionTest, EmailLinkExpirationAuthorizationTest, ProjectPolicyAbilitiesTest

### Community 3 - "CredentialHasher"
Cohesion: 0.21
Nodes (8): AcceptInvitationAction, IssueUserInvitationAction, ResendUserInvitationAction, CredentialHasher, InvitationCredentialGenerator, InvitationLinkRequestIssuer, InvitationRateLimiter, InvitationDeliveryService

### Community 5 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.06
Nodes (11): Permission, Role, User, ExampleTest, AuditLogTest, NotificationDeliveryRetryTest, ServiceClientPermissionTest, UserEmailAddressFactoryTest (+3 more)

### Community 7 - "AuditLogger"
Cohesion: 0.09
Nodes (16): AddProjectMemberAction, AssignProjectOwnerAction, ChangeProjectAutoAssignmentSettingAction, ChangeProjectMemberRoleAction, ChangeProjectOwnerAssignmentTypeAction, RemoveProjectMemberAction, TransferProjectOwnerAction, UnassignProjectOwnerAction (+8 more)

### Community 8 - "RoleSeeder"
Cohesion: 0.11
Nodes (6): ProjectAuthorizationPermissionSeeder, ProjectRoleSeeder, RoleSeeder, ProjectAuthorizationPermissionSeederTest, ProjectRoleSeederTest, RoleSeederTest

### Community 9 - "generate.js"
Cohesion: 0.06
Nodes (23): allNoBorders, ASSETS, body, BODY_FONT, bodyMargin, buildCoverR1(), bullet(), calcCoverSpacing() (+15 more)

### Community 12 - "haraka"
Cohesion: 0.06
Nodes (31): context, dockerfile, driver, DELIVERY_MODE, HARAKA_SMTP_PASSWORD, HARAKA_SMTP_USER, MP_DATABASE, MP_MAX_MESSAGES (+23 more)

### Community 14 - "InvitationLinkRequest"
Cohesion: 0.14
Nodes (3): {closure#9}(), InvitationLinkRequest, InvitationRateLimitException

### Community 15 - "AssignProjectOwnerActionTest"
Cohesion: 0.07
Nodes (4): AssignProjectOwnerActionTest, ChangeAutoAssignmentSettingActionTest, ChangeProjectMemberRoleActionTest, UnassignProjectOwnerActionTest

### Community 16 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.09
Nodes (7): {closure#2}(), EmailVerificationAttemptCounter, EmailVerificationRequest, {closure#1}(), PasswordResetRequest, TestSuite, {closure#1}()

### Community 19 - "Illuminate\Mail\Mailable"
Cohesion: 0.09
Nodes (12): AccountReactivatedMailable, DirectAccountSetupMailable, EmailChangeVerificationMailable, EmailVerificationMailable, InvitationLinkMailable, PasswordChangedMailable, PasswordResetMailable, SystemRoleChangedMailable (+4 more)

### Community 20 - "ReactivateUserActionTest"
Cohesion: 0.13
Nodes (3): LifecycleConnectionGuard, ReactivateUserActionTest, LifecycleConnectionGuardTest

### Community 22 - "AccountLinkExpirationSettings"
Cohesion: 0.20
Nodes (3): SystemSettingAccountLinkExpirationController, AccountLinkExpirationSettings, AccountLinkExpirationSettingsTest

### Community 23 - "OAuthScopes"
Cohesion: 0.20
Nodes (3): AppServiceProvider, {closure#1}(), ScopeRepository

### Community 24 - "PermissionChecker"
Cohesion: 0.15
Nodes (5): ProjectController, EnsurePermission, EnsureUserIsSuperAdmin, PermissionChecker, PermissionCheckerTest

### Community 25 - ".members"
Cohesion: 0.06
Nodes (4): ChangeProjectOwnerAssignmentTypeActionTest, TransferProjectOwnerActionTest, ProjectMembershipHttpTest, ProjectOwnershipHttpTest

### Community 27 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.10
Nodes (13): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}() (+5 more)

### Community 29 - "App\Models\User"
Cohesion: 0.11
Nodes (7): {closure#1}(), CancelEmailChangeAction, RequestEmailChangeAction, {closure#1}(), {closure#2}(), UserEmailChangeController, UserLifecycleAuthorizer

### Community 31 - "package.json"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 37 - "Tests\TestCase"
Cohesion: 0.06
Nodes (6): UserNotificationPreference, NotificationPreferenceChecker, TestSuiteTest, NotificationPreferenceEnforcementTest, NotificationPreferencesTest, NotificationStatusTest

### Community 38 - "NotificationDeliveryRetryServiceTest.php"
Cohesion: 0.06
Nodes (9): NotificationDeliveryHistoryController, NotificationDeliveryRetry, {closure#1}(), {closure#2}(), NotificationDeliveryRetryService, NotificationRetryException, {closure#1}(), {closure#14}() (+1 more)

### Community 43 - "UserInvitation"
Cohesion: 0.19
Nodes (3): {closure#1}(), UserInvitationController, UserInvitation

### Community 46 - "SystemAnnouncementMailable"
Cohesion: 0.22
Nodes (5): SystemAnnouncementMailable, {closure#1}(), {closure#2}(), {closure#3}(), SystemAnnouncementTest

### Community 47 - "AuditLogController.php"
Cohesion: 0.10
Nodes (10): {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}(), {closure#17}(), {closure#4}() (+2 more)

### Community 49 - "App\Services\Audit\AuditLogger"
Cohesion: 0.18
Nodes (7): AssignUserRoleAction, ChangeOwnPasswordAction, DeactivateUserAction, PermanentlyDeleteUserAction, ReactivateUserAction, UserLifecycleController, AccountSecurityNotificationService

### Community 53 - "2026_11_16_000001_create_password_reset_requests_table.php"
Cohesion: 0.33
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), createOutstandingRequestUniqueIndex(), up()

### Community 54 - "App\Http\Controllers\Settings\NotificationDeliveryHistoryController"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 55 - "App\Models\Role"
Cohesion: 0.07
Nodes (5): {closure#5}(), {closure#7}(), UserManagementPermissionSeeder, {closure#1}(), {closure#1}()

### Community 56 - "AccountReactivatedMailable"
Cohesion: 0.18
Nodes (5): AccountDeactivatedMailable, {closure#1}(), {closure#2}(), {closure#3}(), SecurityNotificationTest

### Community 57 - "Closure"
Cohesion: 0.14
Nodes (3): {closure#1}(), EnforceAuthorizationCodePkce, EnsureEmailLinkExpirationAccess

### Community 58 - "App\Services\Authorization\PermissionChecker"
Cohesion: 0.24
Nodes (3): {closure#1}(), UserDirectoryController, UserDeletionDependencyInspector

### Community 59 - "EmailLinkExpirationSetting"
Cohesion: 0.28
Nodes (3): SystemSettingEmailLinkExpirationController, EmailLinkExpirationSetting, EmailLinkExpirationSettingTest

### Community 60 - "App\Services\Audit\AuditLogger"
Cohesion: 0.07
Nodes (9): {closure#2}(), CompleteForcedPasswordResetAction, {closure#1}(), ForcedPasswordResetController, PasswordPolicy, PasswordResetException, PasswordResetService, PasswordPolicyTest (+1 more)

### Community 63 - "NotificationDelivery"
Cohesion: 0.09
Nodes (9): NotificationDelivery, {closure#1}(), {closure#2}(), {closure#3}(), NotificationDeliveryRecorder, {closure#1}(), {closure#1}(), {closure#1}() (+1 more)

### Community 67 - ".log"
Cohesion: 0.19
Nodes (8): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

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
Nodes (8): SystemSetting, AccountCreationPermissionSeeder, AccountLinkExpirationSettingsSeeder, DatabaseSeeder, SuperAdminSeeder, SystemSettingPermissionSeeder, SystemSettingSeeder, TestUserSeeder

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

### Community 104 - ".log"
Cohesion: 0.21
Nodes (9): {closure#1}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#2}() (+1 more)

### Community 107 - "Illuminate\Support\Facades\Schema"
Cohesion: 0.15
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 112 - "Laravel\Passport\Client"
Cohesion: 0.19
Nodes (3): ServiceClientPermission, OAuthScopes, ServiceClientAuthorizer

### Community 114 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 115 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 127 - "bootstrap/app.php"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 175 - "App\Http\Controllers\Controller"
Cohesion: 0.12
Nodes (6): BulkUserController, PermissionController, {closure#1}(), {closure#2}(), RoleController, UserDeletionController

### Community 179 - "InvitationLifecycleTest.php"
Cohesion: 0.33
Nodes (3): CancelUserInvitationAction, RecoverUserInvitationAction, InvitationRecoveryController

### Community 187 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.14
Nodes (17): {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+9 more)

### Community 194 - "ForceUserPasswordResetAction"
Cohesion: 0.39
Nodes (3): ForceUserPasswordResetAction, SetUserPasswordAction, UserPasswordController

### Community 205 - "App\Services\Auth\CredentialHasher"
Cohesion: 0.18
Nodes (6): {closure#1}(), {closure#1}(), {closure#2}(), EmailChangeVerificationController, EmailVerificationController, UserEmailAddress

### Community 211 - "Illuminate\Http\Request"
Cohesion: 0.11
Nodes (7): AuthenticatedSessionController, SystemSettingEmailVerificationLimitController, SystemSettingAccountLinkExpirationResource, SystemSettingEmailLinkExpirationResource, SystemSettingEmailVerificationLimitResource, EmailVerificationLimitSettings, {closure#1}()

## Knowledge Gaps
- **13 isolated node(s):** `php`, `layouts.app`, `layouts.app`, `layouts.app`, `layouts.app` (+8 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 692 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **170 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `Illuminate\Support\Facades\DB`, `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `CredentialHasher`, `AccountLinkExpirationHttpTest`, `RemoveProjectMemberActionTest`, `AuditLogger`, `UserDirectoryTest.php`, `InvitationLinkRequest`, `AssignProjectOwnerActionTest`, `Illuminate\Database\Eloquent\Model`, `ServiceClientAuthorizationTest`, `ReactivateUserActionTest`, `AccountLinkExpirationSettings`, `PermissionChecker`, `.members`, `App\Models\User`, `ProjectReadHttpTest`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `Tests\TestCase`, `NotificationDeliveryRetryServiceTest.php`, `.hash`, `UserInvitation`, `Database\Factories\UserFactory`, `ProjectPolicyTest`, `App\Services\Audit\AuditLogger`, `.consume`, `InvitationAcceptanceHttpTest`, `InvitationLifecycleTest.php`, `InvitationReissueTest`, `UserInvitationHttpTest`, `App\Models\Role`, `Closure`, `App\Services\Authorization\PermissionChecker`, `Illuminate\Database\Eloquent\Builder`, `EmailLinkExpirationSetting`, `App\Actions\AcceptInvitationAction`, `AuthorizationCodePkceTest`, `NotificationDelivery`, `.log`, `.view`, `ProjectFactory.php`, `EmailVerificationHttpTest`, `EmailLinkExpirationAuditTest`, `App\Services\Auth\CredentialHasher`, `Illuminate\Database\Seeder`, `SettingsAuthorizationTest`, `UserInvitationFactory.php`, `InvitationDeliveryIntegrationTest`, `.log`, `Laravel\Passport\Client`?**
  _High betweenness centrality (0.200) - this node is a cross-community bridge._
- **What connects `php`, `layouts.app`, `layouts.app` to the rest of the system?**
  _13 weakly-connected nodes found - possible missing edges._
- **Should `Illuminate\Support\Facades\DB` be split into smaller, more focused modules?**
  _Cohesion score 0.1 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `Illuminate\Support\Facades\DB`, `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `CredentialHasher`, `AccountLinkExpirationHttpTest`, `RemoveProjectMemberActionTest`, `RoleSeeder`, `UserDirectoryTest.php`, `EmailVerificationService`, `AssignProjectOwnerActionTest`, `ServiceClientAuthorizationTest`, `ReactivateUserActionTest`, `AccountLinkExpirationSettings`, `PermissionChecker`, `.members`, `ProjectReadHttpTest`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `Tests\TestCase`, `NotificationDeliveryRetryServiceTest.php`, `ProjectOwnershipTest`, `InvitationLifecycleTest`, `ProjectPolicyTest`, `UserInvitationHttpTest`, `.consume`, `InvitationAcceptanceHttpTest`, `InvitationLifecycleTest.php`, `InvitationReissueTest`, `AuthorizationCodePkceMiddlewareTest`, `AuthorizationCodePkceTest`, `EmailLinkExpirationSetting`, `NotificationDelivery`, `PermissionMiddlewareTest`, `EmailVerificationHttpTest`, `ProjectRelationshipTest`, `EmailLinkExpirationAuditTest`, `InvitationRecoveryHttpTest`, `Illuminate\Database\Seeder`, `SettingsAuthorizationTest`, `OAuthScopeIssuanceTest`, `AuthenticatedShellTest`, `AuditLoggerTest`, `PassportConfigurationTest`, `AuthorizationCodePkceMiddlewareTest.php`, `InvitationDeliveryIntegrationTest`, `NotificationDeliveryTest`, `Laravel\Passport\Client`?**
  _High betweenness centrality (0.091) - this node is a cross-community bridge._
- **Should `DeactivateUserActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.09711779448621553 - nodes in this community are weakly interconnected._
- **Why does `UserInvitation` connect `UserInvitation` to `Illuminate\Support\Facades\DB`, `CredentialHasher`, `Illuminate\Foundation\Testing\RefreshDatabase`, `.log`, `.hash`, `InvitationLinkRequest`, `Illuminate\Database\Eloquent\Model`, `InvitationLifecycleTest.php`, `UserInvitationFactory.php`, `App\Actions\AcceptInvitationAction`?**
  _High betweenness centrality (0.031) - this node is a cross-community bridge._
- **Should `AddProjectMemberActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.09059029807130334 - nodes in this community are weakly interconnected._
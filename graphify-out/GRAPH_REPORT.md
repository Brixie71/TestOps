# Graph Report - TestOps  (2026-11-19)

## Corpus Check
- 336 files · ~225,343 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 28 file(s) not represented in the graph (top: (none) 20, .ini 3, .example 2)

## Summary
- 2604 nodes · 6931 edges · 228 communities (57 shown, 171 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 493 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `813bc7e3`
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
- Illuminate\Contracts\View\View
- haraka
- EmailVerificationService
- PasswordPolicy
- ReactivateUserActionTest
- Illuminate\Database\Eloquent\Model
- Tests\TestCase
- ChangeProjectMemberRoleActionTest
- App\Services\Notifications\InvitationDeliveryService
- App\Services\Audit\AuditLogger
- PasswordChangeTest
- AccountLinkExpirationSettings
- OAuthScopes
- AssignProjectOwnerActionTest
- .members
- UserPasswordManagementTest
- Illuminate\Database\Schema\Blueprint
- PasswordResetRequest
- Laravel\Passport\Client
- InvitationDeliveryIntegrationTest
- package.json
- web.php
- App\Http\Controllers\ProjectController
- EnsureActiveAccountMiddlewareTest.php
- UserRoleAssignmentTest
- AuditLogPageTest
- AccountLinkExpirationHttpTest
- AdminProfileUpdateTest
- ProjectOwnershipTest
- UserLifecycleHttpTest
- InvitationLifecycleTest
- UserInvitation
- CredentialHasher
- NotificationDeliveryHistoryTest
- AssignProjectOwnerActionTest
- ProjectPolicyTest
- EmailVerificationRequest
- AuditLogger
- ProfileUpdateTest
- Carbon\CarbonInterface
- InvitationAcceptanceHttpTest
- InvitationReissueTest
- 2026_11_16_000001_create_password_reset_requests_table.php
- App\Http\Controllers\Settings\NotificationDeliveryHistoryController
- SecurityNotificationTest.php
- InvitationLinkException
- PermissionChecker
- NotificationDeliveryRetryServiceTest.php
- 2026_11_05_005052_create_system_settings_table.php
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
- 2026_11_04_070639_add_profile_fields_to_users_table.php
- 2026_11_04_070855_add_deleted_at_to_users_table.php
- 2026_11_09_041659_extend_user_invitations_table_for_account_creation_module.php
- Illuminate\Database\Migrations\Migration
- 2026_11_05_074100_create_oauth_access_tokens_table.php
- 2026_11_05_074101_create_oauth_refresh_tokens_table.php
- 2026_11_05_074102_create_oauth_clients_table.php
- 2026_11_05_074103_create_oauth_device_codes_table.php
- 2026_11_05_102745_create_service_client_permissions_table.php
- 2026_11_09_041659_create_email_verification_requests_table.php
- 2026_11_09_041659_make_users_name_nullable.php
- 2026_11_02_045314_create_permissions_table.php
- AuthorizationCodePkceTest
- App\Models\User
- 2026_11_05_193030_create_user_invitations_table.php
- 2026_11_02_045840_create_permission_role_table.php
- NotificationDeliveryTest
- EmailVerificationRequestFactory
- App\Http\Middleware\EnsureEmailLinkExpirationAccess
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
- UpdateUserProfileAction
- UserDetailsPageTest
- NotificationDeliveryRetry
- layouts.settings
- BulkUserOperationsTest
- PermanentlyDeleteUserActionTest
- .retry
- UserInvitationHttpTest
- EmailVerificationIssuanceLimitsTest
- InvitationDeliveryService
- AuthorizationCodePkceMiddlewareTest
- EmailVerificationLimitSettingsHttpTest
- Illuminate\Database\Eloquent\Factories\Factory
- App\Http\Controllers\Settings\AuditLogController
- 2026_11_05_074059_create_oauth_auth_codes_table.php
- {closure#1}
- 2026_11_06_055820_create_notification_deliveries_table.php
- Illuminate\Database\Eloquent\Builder
- bootstrap/app.php
- InvitationLinkRequestFactory
- Illuminate\Http\RedirectResponse
- PermanentUserDeletionHttpTest
- User
- Illuminate\Http\Request
- NotificationDelivery
- PassportConfigurationTest
- ProjectPolicyAbilitiesTest
- .allows
- InvitationRecoveryHttpTest
- OAuthScopeIssuanceTest
- EmailVerificationServiceTest.php
- AuthenticatedShellTest
- NotificationDeliveryRetryTest
- ServiceClientPermissionTest
- AuditLoggerTest
- AuditLogTest
- EmailVerificationRateLimitException
- EmailVerificationHttpTest
- .log
- Database\Factories\UserFactory
- EmailVerificationAttemptCounter
- UserEmailAddressFactoryTest
- ExampleTest

## God Nodes (most connected - your core abstractions)
1. `User` - 214 edges
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
- `DeactivateUserActionTest` --references--> `DeactivateUserAction`  [EXTRACTED]
  tests/Feature/Actions/DeactivateUserActionTest.php → app/Actions/DeactivateUserAction.php
- `ReactivateUserActionTest` --references--> `ReactivateUserAction`  [EXTRACTED]
  tests/Feature/Actions/ReactivateUserActionTest.php → app/Actions/ReactivateUserAction.php
- `EmailVerificationHttpTest` --references--> `EmailVerificationService`  [EXTRACTED]
  tests/Feature/Auth/EmailVerificationHttpTest.php → app/Services/Auth/EmailVerificationService.php
- `EmailVerificationIssuanceLimitsTest` --references--> `EmailVerificationService`  [EXTRACTED]
  tests/Feature/Auth/EmailVerificationIssuanceLimitsTest.php → app/Services/Auth/EmailVerificationService.php

## Import Cycles
- None detected.

## Communities (228 total, 171 thin omitted)

### Community 0 - "App\Actions\RequestEmailChangeAction"
Cohesion: 0.20
Nodes (3): {closure#2}(), RequestEmailChangeAction, ProfileController

### Community 1 - "DeactivateUserActionTest"
Cohesion: 0.10
Nodes (3): RevokesUserCredentials, DeactivateUserActionTest, RevokesUserCredentialsTest

### Community 5 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.08
Nodes (6): AuditLog, Permission, Role, User, {closure#1}(), TestCase

### Community 7 - "AuditLogger"
Cohesion: 0.10
Nodes (19): AddProjectMemberAction, {closure#1}(), AssignProjectOwnerAction, {closure#1}(), ChangeProjectAutoAssignmentSettingAction, {closure#1}(), ChangeProjectMemberRoleAction, {closure#1}() (+11 more)

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
Cohesion: 0.25
Nodes (3): CompleteEmailChangeAction, EmailVerificationService, EmailVerificationServiceTest

### Community 16 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.12
Nodes (3): {closure#1}(), PasswordResetRequest, ServiceClientPermission

### Community 17 - "Tests\TestCase"
Cohesion: 0.08
Nodes (3): {closure#1}(), {closure#1}(), NotificationStatusTest

### Community 19 - "App\Services\Notifications\InvitationDeliveryService"
Cohesion: 0.14
Nodes (11): DirectAccountSetupMailable, EmailChangeCancelledMailable, EmailChangeVerificationMailable, EmailVerificationMailable, PasswordChangedMailable, PasswordResetMailable, {closure#1}(), {closure#1}() (+3 more)

### Community 20 - "App\Services\Audit\AuditLogger"
Cohesion: 0.19
Nodes (6): ChangeOwnPasswordAction, DeactivateUserAction, PermanentlyDeleteUserAction, ReactivateUserAction, UserLifecycleController, AccountSecurityNotificationService

### Community 22 - "AccountLinkExpirationSettings"
Cohesion: 0.19
Nodes (4): {closure#2}(), SystemSettingAccountLinkExpirationController, AccountLinkExpirationSettings, AccountLinkExpirationSettingsTest

### Community 23 - "OAuthScopes"
Cohesion: 0.20
Nodes (3): AppServiceProvider, {closure#1}(), ScopeRepository

### Community 24 - "AssignProjectOwnerActionTest"
Cohesion: 0.09
Nodes (3): ChangeAutoAssignmentSettingActionTest, RemoveProjectMemberActionTest, UnassignProjectOwnerActionTest

### Community 25 - ".members"
Cohesion: 0.06
Nodes (4): ChangeProjectOwnerAssignmentTypeActionTest, TransferProjectOwnerActionTest, ProjectMembershipHttpTest, ProjectOwnershipHttpTest

### Community 27 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.12
Nodes (11): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}() (+3 more)

### Community 30 - "InvitationDeliveryIntegrationTest"
Cohesion: 0.30
Nodes (3): {closure#1}(), ResendUserInvitationAction, InvitationDeliveryIntegrationTest

### Community 31 - "package.json"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 33 - "App\Http\Controllers\ProjectController"
Cohesion: 0.18
Nodes (8): {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}()

### Community 43 - "CredentialHasher"
Cohesion: 0.14
Nodes (6): IssueUserInvitationAction, InvitationLinkRequest, CredentialHasher, InvitationCredentialGenerator, InvitationLinkRequestIssuer, InvitationRateLimiter

### Community 47 - "EmailVerificationRequest"
Cohesion: 0.19
Nodes (7): {closure#1}(), {closure#1}(), {closure#2}(), EmailChangeVerificationController, EmailVerificationController, EmailVerificationRequest, UserEmailAddress

### Community 48 - "AuditLogger"
Cohesion: 0.16
Nodes (5): SystemSettingEmailLinkExpirationController, SystemSetting, AuditLogger, EmailLinkExpirationSetting, EmailLinkExpirationSettingTest

### Community 53 - "2026_11_16_000001_create_password_reset_requests_table.php"
Cohesion: 0.33
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), createOutstandingRequestUniqueIndex(), up()

### Community 54 - "App\Http\Controllers\Settings\NotificationDeliveryHistoryController"
Cohesion: 0.24
Nodes (7): {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}(), NotificationDeliveryHistoryController, {closure#2}(), SecurityNotificationRetryService

### Community 56 - "SecurityNotificationTest.php"
Cohesion: 0.12
Nodes (7): AccountDeactivatedMailable, AccountReactivatedMailable, SystemRoleChangedMailable, {closure#1}(), {closure#2}(), {closure#3}(), SecurityNotificationTest

### Community 58 - "PermissionChecker"
Cohesion: 0.15
Nodes (5): {closure#1}(), EnforceAuthorizationCodePkce, EnsurePermission, EnsureUserIsSuperAdmin, PermissionChecker

### Community 61 - "PasswordPolicy"
Cohesion: 0.21
Nodes (4): ForceUserPasswordResetAction, SetUserPasswordAction, UserPasswordController, PasswordPolicy

### Community 63 - "InvitationDeliveryService"
Cohesion: 0.12
Nodes (3): NotificationDeliveryRecorder, {closure#1}(), NotificationDeliveryRecorderTest

### Community 67 - "PasswordResetException"
Cohesion: 0.15
Nodes (6): {closure#2}(), CompleteForcedPasswordResetAction, {closure#1}(), ForcedPasswordResetController, PasswordResetException, PasswordResetService

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

### Community 95 - "Illuminate\Database\Migrations\Migration"
Cohesion: 0.12
Nodes (3): {closure#1}(), {closure#1}(), {closure#1}()

### Community 101 - "2026_11_09_041659_create_email_verification_requests_table.php"
Cohesion: 0.50
Nodes (3): {closure#1}(), createOutstandingRequestUniqueIndex(), up()

### Community 106 - "App\Models\User"
Cohesion: 0.10
Nodes (10): {closure#1}(), {closure#1}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}(), UserDirectoryController (+2 more)

### Community 112 - "App\Http\Middleware\EnsureEmailLinkExpirationAccess"
Cohesion: 0.19
Nodes (3): EnsureEmailLinkExpirationAccess, ServiceClientAuthorizer, {closure#1}()

### Community 114 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 115 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 174 - "PermanentlyDeleteUserActionTest"
Cohesion: 0.17
Nodes (3): LifecycleConnectionGuard, PermanentlyDeleteUserActionTest, LifecycleConnectionGuardTest

### Community 179 - "InvitationDeliveryService"
Cohesion: 0.17
Nodes (7): AcceptInvitationAction, CancelUserInvitationAction, RecoverUserInvitationAction, InvitationRecoveryController, Controller, UserInvitationController, InvitationDeliveryService

### Community 187 - "App\Http\Controllers\Settings\AuditLogController"
Cohesion: 0.13
Nodes (8): {closure#10}(), {closure#2}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}()

### Community 191 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.27
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#9}()

### Community 192 - "bootstrap/app.php"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 194 - "Illuminate\Http\RedirectResponse"
Cohesion: 0.11
Nodes (7): AssignUserRoleAction, CancelEmailChangeAction, AuthenticatedSessionController, BulkUserController, UserDeletionController, UserEmailChangeController, UserRoleController

### Community 197 - "Illuminate\Http\Request"
Cohesion: 0.25
Nodes (3): SystemSettingAccountLinkExpirationResource, SystemSettingEmailLinkExpirationResource, SystemSettingEmailVerificationLimitResource

### Community 198 - "NotificationDelivery"
Cohesion: 0.24
Nodes (4): NotificationDelivery, {closure#1}(), {closure#2}(), {closure#1}()

### Community 203 - "InvitationRecoveryHttpTest"
Cohesion: 0.23
Nodes (4): InvitationLinkMailable, {closure#1}(), InvitationRecoveryHttpTest, {closure#1}()

### Community 218 - ".log"
Cohesion: 0.36
Nodes (6): {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

## Knowledge Gaps
- **10 isolated node(s):** `php`, `layouts.app`, `layouts.app`, `layouts.app`, `layouts.app` (+5 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 650 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **171 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `ProjectReadHttpTest`, `ServiceClientAuthorizationTest`, `AuditLogger`, `RoleSeeder`, `UserDirectoryTest.php`, `ReactivateUserActionTest`, `Illuminate\Database\Eloquent\Model`, `Tests\TestCase`, `ChangeProjectMemberRoleActionTest`, `App\Services\Audit\AuditLogger`, `AccountLinkExpirationSettings`, `AssignProjectOwnerActionTest`, `.members`, `Laravel\Passport\Client`, `InvitationDeliveryIntegrationTest`, `App\Http\Controllers\ProjectController`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `AccountLinkExpirationHttpTest`, `UserInvitation`, `CredentialHasher`, `NotificationDeliveryRetry`, `AssignProjectOwnerActionTest`, `ProjectPolicyTest`, `EmailVerificationRequest`, `.retry`, `AuditLogger`, `Carbon\CarbonInterface`, `InvitationDeliveryService`, `InvitationAcceptanceHttpTest`, `InvitationReissueTest`, `App\Http\Controllers\Settings\NotificationDeliveryHistoryController`, `UserInvitationHttpTest`, `NotificationDeliveryRetryServiceTest.php`, `InvitationDeliveryService`, `Illuminate\Http\RedirectResponse`, `User`, `ProjectFactory.php`, `NotificationDelivery`, `ProjectPolicyAbilitiesTest`, `.allows`, `InvitationRecoveryHttpTest`, `EmailLinkExpirationAuditTest`, `EmailVerificationServiceTest.php`, `SettingsAuthorizationTest`, `EmailVerificationHttpTest`, `UserDetailsPageTest`, `.log`, `Database\Factories\UserFactory`, `AuthorizationCodePkceTest`, `App\Models\User`, `App\Http\Middleware\EnsureEmailLinkExpirationAccess`?**
  _High betweenness centrality (0.202) - this node is a cross-community bridge._
- **What connects `php`, `layouts.app`, `layouts.app` to the rest of the system?**
  _10 weakly-connected nodes found - possible missing edges._
- **Should `DeactivateUserActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.09711779448621553 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `ProjectReadHttpTest`, `ServiceClientAuthorizationTest`, `RoleSeeder`, `UserDirectoryTest.php`, `EmailVerificationService`, `ReactivateUserActionTest`, `Tests\TestCase`, `ChangeProjectMemberRoleActionTest`, `AccountLinkExpirationSettings`, `AssignProjectOwnerActionTest`, `.members`, `Laravel\Passport\Client`, `InvitationDeliveryIntegrationTest`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `AccountLinkExpirationHttpTest`, `ProjectOwnershipTest`, `InvitationLifecycleTest`, `UserDetailsPageTest`, `CredentialHasher`, `AssignProjectOwnerActionTest`, `ProjectPolicyTest`, `.retry`, `PermanentlyDeleteUserActionTest`, `UserInvitationHttpTest`, `AuditLogger`, `InvitationAcceptanceHttpTest`, `InvitationDeliveryService`, `InvitationReissueTest`, `AuthorizationCodePkceMiddlewareTest`, `NotificationDeliveryRetryServiceTest.php`, `InvitationDeliveryService`, `PermissionMiddlewareTest`, `PassportConfigurationTest`, `ProjectPolicyAbilitiesTest`, `ProjectRelationshipTest`, `EmailLinkExpirationAuditTest`, `InvitationRecoveryHttpTest`, `OAuthScopeIssuanceTest`, `EmailVerificationServiceTest.php`, `.allows`, `SettingsAuthorizationTest`, `AuthenticatedShellTest`, `NotificationDeliveryRetryTest`, `ServiceClientPermissionTest`, `AuditLoggerTest`, `AuditLogTest`, `EmailVerificationHttpTest`, `UserEmailAddressFactoryTest`, `ExampleTest`, `AuthorizationCodePkceTest`, `NotificationDeliveryTest`?**
  _High betweenness centrality (0.066) - this node is a cross-community bridge._
- **Should `ProjectReadHttpTest` be split into smaller, more focused modules?**
  _Cohesion score 0.1476923076923077 - nodes in this community are weakly interconnected._
- **Why does `Role` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `AddProjectMemberActionTest`, `ProjectReadHttpTest`, `ServiceClientAuthorizationTest`, `AuditLogger`, `RoleSeeder`, `UserDirectoryTest.php`, `ReactivateUserActionTest`, `Illuminate\Database\Eloquent\Model`, `Tests\TestCase`, `ChangeProjectMemberRoleActionTest`, `AssignProjectOwnerActionTest`, `.members`, `Laravel\Passport\Client`, `InvitationDeliveryIntegrationTest`, `App\Http\Controllers\ProjectController`, `UserRoleAssignmentTest`, `AccountLinkExpirationHttpTest`, `UserDetailsPageTest`, `AssignProjectOwnerActionTest`, `ProjectPolicyTest`, `Carbon\CarbonInterface`, `InvitationDeliveryService`, `NotificationDeliveryRetryServiceTest.php`, `PermissionMiddlewareTest`, `Illuminate\Http\RedirectResponse`, `User`, `ProjectPolicyAbilitiesTest`, `.allows`, `Illuminate\Database\Seeder`?**
  _High betweenness centrality (0.034) - this node is a cross-community bridge._
- **Should `Illuminate\Foundation\Testing\RefreshDatabase` be split into smaller, more focused modules?**
  _Cohesion score 0.08016877637130802 - nodes in this community are weakly interconnected._
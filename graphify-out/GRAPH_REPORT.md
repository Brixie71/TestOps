# Graph Report - TestOps  (2026-11-16)

## Corpus Check
- 289 files · ~208,277 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 28 file(s) not represented in the graph (top: (none) 20, .ini 3, .example 2)

## Summary
- 2262 nodes · 5827 edges · 187 communities (46 shown, 141 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 402 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `1ba03984`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- .members
- DeactivateUserActionTest
- Illuminate\Support\Facades\DB
- EmailVerificationService
- AddProjectMemberActionTest
- Illuminate\Foundation\Testing\RefreshDatabase
- User
- AuditLogger
- RoleSeeder
- generate.js
- UserDirectoryTest.php
- TransferProjectOwnerActionTest
- haraka
- NotificationDelivery
- PasswordResetService
- RecoverUserInvitationAction.php
- Illuminate\Database\Eloquent\Model
- Laravel\Passport\Client
- App\Models\NotificationDelivery
- InvitationLinkMailable
- .log
- EmailLinkExpirationSetting
- AccountLinkExpirationSettings
- OAuthScopes
- AssignProjectOwnerActionTest
- ChangeProjectMemberRoleActionTest
- UserPasswordManagementTest
- Illuminate\Database\Schema\Blueprint
- PasswordResetRequest
- ServiceClientAuthorizationTest
- InvitationDeliveryIntegrationTest
- package.json
- web.php
- Illuminate\Database\Eloquent\Builder
- EnsureActiveAccountMiddlewareTest.php
- UserRoleAssignmentTest
- AuditLogPageTest
- PassportConfigurationTest
- ChangeAutoAssignmentSettingActionTest
- ProjectOwnershipTest
- UserLifecycleHttpTest
- InvitationLifecycleTest
- UserInvitation
- CredentialHasher
- NotificationDeliveryHistoryTest
- PasswordResetRequestFactory.php
- ProjectPolicyTest
- UserInvitationHttpTest
- App\Http\Controllers\Settings\UserRoleController
- .retry
- ServiceClientPermissionTest
- InvitationAcceptanceHttpTest
- InvitationReissueTest
- 2026_11_16_000001_create_password_reset_requests_table.php
- InvitationDeliveryService
- AuthorizationCodePkceMiddlewareTest
- InvitationLinkException
- PermissionChecker
- OAuthScopeIssuanceTest
- 2026_11_05_005052_create_system_settings_table.php
- PermissionChecker
- AccountLinkExpirationHttpTest
- ProjectPolicyAbilitiesTest
- NotificationDeliveryRetry
- UserFactory.php
- NotificationDeliveryRetryFactory.php
- NotificationDeliveryRetryServiceTest.php
- ProjectFactory.php
- UserFactory
- Illuminate\Database\Eloquent\Relations\BelongsTo
- Illuminate\Http\Request
- UserInvitationFactory.php
- ProjectRelationshipTest
- EmailLinkExpirationAuditTest
- composer.json
- require-dev
- scripts
- Illuminate\Support\Facades\Schema
- PermissionMiddlewareTest
- SettingsAuthorizationTest
- 0001_01_01_000001_create_cache_table.php
- config
- EmailVerificationRequestFactory.php
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
- 2026_11_02_051649_create_projects_table.php
- BulkUserController
- 2026_11_05_193030_create_user_invitations_table.php
- 2026_11_02_045840_create_permission_role_table.php
- 2026_11_06_062802_create_notification_delivery_retries_table.php
- 2026_11_09_041659_create_invitation_link_requests_table.php
- PasswordPolicyTest
- autoload-dev
- extra
- laravel-boost
- dashboard/index.blade.php
- settings.blade.php
- profile/index.blade.php
- projects/index.blade.php
- show.blade.php
- about.blade.php
- settings/index.blade.php
- permissions.blade.php
- roles.blade.php
- AuthorizationCodePkceTest
- UserDetailsPageTest
- App\Models\User
- layouts.settings
- BulkUserOperationsTest
- {closure#1}
- AuthenticatedShellTest
- AuditLoggerTest
- NotificationDelivery
- Illuminate\Database\Seeder
- AuditLogController
- Illuminate\Database\Eloquent\Factories\Factory
- InvitationLinkRequestFactory

## God Nodes (most connected - your core abstractions)
1. `User` - 228 edges
2. `TestCase` - 116 edges
3. `Role` - 94 edges
4. `AuditLogger` - 81 edges
5. `Project` - 79 edges
6. `AccountLinkExpirationSettings` - 41 edges
7. `ProjectMembershipRules` - 40 edges
8. `RoleSeeder` - 39 edges
9. `EmailVerificationService` - 38 edges
10. `UserInvitation` - 38 edges

## Surprising Connections (you probably didn't know these)
- `{closure#16}()` --references--> `User`  [EXTRACTED]
  tests/Feature/Settings/UserDirectoryTest.php → app/Models/User.php
- `{closure#4}()` --references--> `User`  [EXTRACTED]
  database/factories/UserFactory.php → app/Models/User.php
- `{closure#1}()` --references--> `NotificationDelivery`  [EXTRACTED]
  tests/Feature/Services/Notifications/NotificationDeliveryRetryServiceTest.php → app/Models/NotificationDelivery.php
- `{closure#1}()` --references--> `InvitationLinkMailable`  [EXTRACTED]
  tests/Feature/Auth/InvitationRecoveryHttpTest.php → app/Mail/InvitationLinkMailable.php
- `{closure#1}()` --references--> `InvitationLinkMailable`  [EXTRACTED]
  tests/Feature/Settings/UserInvitationHttpTest.php → app/Mail/InvitationLinkMailable.php

## Import Cycles
- None detected.

## Communities (187 total, 141 thin omitted)

### Community 0 - ".members"
Cohesion: 0.05
Nodes (4): ChangeProjectOwnerAssignmentTypeActionTest, ProjectMembershipHttpTest, ProjectOwnershipHttpTest, ProjectReadHttpTest

### Community 1 - "DeactivateUserActionTest"
Cohesion: 0.05
Nodes (10): DeactivateUserAction, ReactivateUserAction, {closure#3}(), UserLifecycleController, RevokesUserCredentials, LifecycleConnectionGuard, DeactivateUserActionTest, ReactivateUserActionTest (+2 more)

### Community 2 - "Illuminate\Support\Facades\DB"
Cohesion: 0.09
Nodes (3): AuditLog, Role, {closure#1}()

### Community 3 - "EmailVerificationService"
Cohesion: 0.08
Nodes (9): {closure#1}(), CompleteAccountActivationAction, EmailVerificationController, EmailVerificationRequest, UserEmailAddress, EmailVerificationException, EmailVerificationService, EmailVerificationHttpTest (+1 more)

### Community 5 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.07
Nodes (8): Permission, ExampleTest, AuditLogTest, NotificationDeliveryRetryTest, NotificationDeliveryTest, UserEmailAddressFactoryTest, {closure#1}(), TestCase

### Community 6 - "User"
Cohesion: 0.12
Nodes (3): Project, User, ProjectPolicy

### Community 7 - "AuditLogger"
Cohesion: 0.17
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

### Community 13 - "NotificationDelivery"
Cohesion: 0.20
Nodes (3): NotificationDeliveryRecorder, {closure#1}(), NotificationDeliveryRecorderTest

### Community 14 - "PasswordResetService"
Cohesion: 0.08
Nodes (10): {closure#2}(), CompleteForcedPasswordResetAction, ForceUserPasswordResetAction, SetUserPasswordAction, ForcedPasswordResetController, InvitationAcceptanceController, UserPasswordController, PasswordPolicy (+2 more)

### Community 19 - "InvitationLinkMailable"
Cohesion: 0.12
Nodes (7): DirectAccountSetupMailable, EmailVerificationMailable, InvitationLinkMailable, PasswordResetMailable, {closure#1}(), InvitationRecoveryHttpTest, {closure#1}()

### Community 20 - ".log"
Cohesion: 0.19
Nodes (10): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}() (+2 more)

### Community 21 - "EmailLinkExpirationSetting"
Cohesion: 0.24
Nodes (3): SystemSettingEmailLinkExpirationController, EmailLinkExpirationSetting, EmailLinkExpirationSettingTest

### Community 22 - "AccountLinkExpirationSettings"
Cohesion: 0.20
Nodes (3): SystemSettingAccountLinkExpirationController, AccountLinkExpirationSettings, AccountLinkExpirationSettingsTest

### Community 23 - "OAuthScopes"
Cohesion: 0.20
Nodes (3): AppServiceProvider, {closure#1}(), ScopeRepository

### Community 27 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.16
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}()

### Community 31 - "package.json"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 33 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.08
Nodes (26): {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+18 more)

### Community 38 - "ChangeAutoAssignmentSettingActionTest"
Cohesion: 0.09
Nodes (3): ChangeAutoAssignmentSettingActionTest, RemoveProjectMemberActionTest, UnassignProjectOwnerActionTest

### Community 42 - "UserInvitation"
Cohesion: 0.23
Nodes (3): {closure#1}(), UserInvitationController, UserInvitation

### Community 43 - "CredentialHasher"
Cohesion: 0.17
Nodes (6): {closure#1}(), {closure#2}(), ResendUserInvitationAction, CredentialHasher, InvitationCredentialGenerator, InvitationLinkRequestIssuer

### Community 48 - "App\Http\Controllers\Settings\UserRoleController"
Cohesion: 0.31
Nodes (3): AssignUserRoleAction, {closure#2}(), UserRoleController

### Community 53 - "2026_11_16_000001_create_password_reset_requests_table.php"
Cohesion: 0.33
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), createOutstandingRequestUniqueIndex(), up()

### Community 55 - "InvitationDeliveryService"
Cohesion: 0.14
Nodes (8): AcceptInvitationAction, CancelUserInvitationAction, IssueUserInvitationAction, RecoverUserInvitationAction, InvitationRecoveryController, Controller, InvitationLinkRequest, InvitationDeliveryService

### Community 58 - "PermissionChecker"
Cohesion: 0.10
Nodes (8): {closure#1}(), EnforceAuthorizationCodePkce, EnsureEmailLinkExpirationAccess, EnsurePermission, EnsureUserIsSuperAdmin, PermissionChecker, ServiceClientAuthorizer, {closure#1}()

### Community 61 - "PermissionChecker"
Cohesion: 0.16
Nodes (3): ProjectController, UserDirectoryController, PermissionCheckerTest

### Community 64 - "NotificationDeliveryRetry"
Cohesion: 0.19
Nodes (4): NotificationDeliveryHistoryController, NotificationDeliveryRetry, {closure#2}(), NotificationDeliveryRetryService

### Community 71 - "Illuminate\Http\Request"
Cohesion: 0.12
Nodes (6): AuthenticatedSessionController, SystemSettingAccountLinkExpirationResource, SystemSettingEmailLinkExpirationResource, {closure#1}(), {closure#2}(), {closure#3}()

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
Cohesion: 0.40
Nodes (5): require, laravel/framework, laravel/passport, laravel/tinker, php

### Community 101 - "2026_11_09_041659_create_email_verification_requests_table.php"
Cohesion: 0.50
Nodes (3): {closure#1}(), createOutstandingRequestUniqueIndex(), up()

### Community 114 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 115 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 171 - "App\Models\User"
Cohesion: 0.16
Nodes (5): {closure#1}(), {closure#1}(), {closure#1}(), {closure#2}(), UserLifecycleAuthorizer

### Community 178 - "NotificationDelivery"
Cohesion: 0.27
Nodes (4): NotificationDelivery, {closure#1}(), {closure#2}(), {closure#1}()

### Community 179 - "Illuminate\Database\Seeder"
Cohesion: 0.09
Nodes (8): SystemSetting, AccountCreationPermissionSeeder, AccountLinkExpirationSettingsSeeder, DatabaseSeeder, SuperAdminSeeder, SystemSettingPermissionSeeder, SystemSettingSeeder, TestUserSeeder

## Knowledge Gaps
- **10 isolated node(s):** `php`, `layouts.app`, `layouts.app`, `layouts.app`, `layouts.app` (+5 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 592 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **141 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `.members`, `DeactivateUserActionTest`, `Illuminate\Support\Facades\DB`, `EmailVerificationService`, `AddProjectMemberActionTest`, `Illuminate\Foundation\Testing\RefreshDatabase`, `AuditLogger`, `RoleSeeder`, `UserDirectoryTest.php`, `TransferProjectOwnerActionTest`, `NotificationDelivery`, `PasswordResetService`, `RecoverUserInvitationAction.php`, `Illuminate\Database\Eloquent\Model`, `Laravel\Passport\Client`, `App\Models\NotificationDelivery`, `.log`, `EmailLinkExpirationSetting`, `AccountLinkExpirationSettings`, `AssignProjectOwnerActionTest`, `ChangeProjectMemberRoleActionTest`, `ServiceClientAuthorizationTest`, `InvitationDeliveryIntegrationTest`, `Illuminate\Database\Eloquent\Builder`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `ChangeAutoAssignmentSettingActionTest`, `UserInvitation`, `CredentialHasher`, `App\Models\User`, `UserDetailsPageTest`, `{closure#1}`, `ProjectPolicyTest`, `App\Http\Controllers\Settings\UserRoleController`, `.retry`, `NotificationDelivery`, `Illuminate\Database\Seeder`, `InvitationAcceptanceHttpTest`, `InvitationReissueTest`, `AuthenticatedShellTest`, `InvitationDeliveryService`, `UserInvitationHttpTest`, `PermissionChecker`, `Illuminate\Database\Eloquent\Relations\HasMany`, `EmailVerificationRequestFactory.php`, `PermissionChecker`, `AccountLinkExpirationHttpTest`, `ProjectPolicyAbilitiesTest`, `NotificationDeliveryRetry`, `UserFactory.php`, `NotificationDeliveryRetryServiceTest.php`, `ProjectFactory.php`, `EmailLinkExpirationAuditTest`, `SettingsAuthorizationTest`, `AuthorizationCodePkceTest`?**
  _High betweenness centrality (0.260) - this node is a cross-community bridge._
- **What connects `php`, `layouts.app`, `layouts.app` to the rest of the system?**
  _10 weakly-connected nodes found - possible missing edges._
- **Should `.members` be split into smaller, more focused modules?**
  _Cohesion score 0.05422838031533684 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `.members`, `DeactivateUserActionTest`, `Illuminate\Support\Facades\DB`, `EmailVerificationService`, `AddProjectMemberActionTest`, `User`, `AuditLogger`, `RoleSeeder`, `UserDirectoryTest.php`, `TransferProjectOwnerActionTest`, `NotificationDelivery`, `Laravel\Passport\Client`, `InvitationLinkMailable`, `EmailLinkExpirationSetting`, `AccountLinkExpirationSettings`, `AssignProjectOwnerActionTest`, `ChangeProjectMemberRoleActionTest`, `PasswordResetRequest`, `ServiceClientAuthorizationTest`, `InvitationDeliveryIntegrationTest`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `PassportConfigurationTest`, `ChangeAutoAssignmentSettingActionTest`, `ProjectOwnershipTest`, `InvitationLifecycleTest`, `UserDetailsPageTest`, `ProjectPolicyTest`, `AuthenticatedShellTest`, `UserInvitationHttpTest`, `AuditLoggerTest`, `ServiceClientPermissionTest`, `InvitationAcceptanceHttpTest`, `InvitationReissueTest`, `.retry`, `Illuminate\Database\Seeder`, `InvitationDeliveryService`, `AuthorizationCodePkceMiddlewareTest`, `OAuthScopeIssuanceTest`, `PermissionChecker`, `AccountLinkExpirationHttpTest`, `ProjectPolicyAbilitiesTest`, `NotificationDeliveryRetryServiceTest.php`, `ProjectRelationshipTest`, `EmailLinkExpirationAuditTest`, `PermissionMiddlewareTest`, `SettingsAuthorizationTest`, `AuthorizationCodePkceTest`?**
  _High betweenness centrality (0.122) - this node is a cross-community bridge._
- **Should `DeactivateUserActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.05215419501133787 - nodes in this community are weakly interconnected._
- **Why does `Role` connect `Illuminate\Support\Facades\DB` to `.members`, `DeactivateUserActionTest`, `AddProjectMemberActionTest`, `Illuminate\Foundation\Testing\RefreshDatabase`, `User`, `AuditLogger`, `RoleSeeder`, `UserDirectoryTest.php`, `TransferProjectOwnerActionTest`, `Illuminate\Database\Eloquent\Model`, `Laravel\Passport\Client`, `.log`, `AssignProjectOwnerActionTest`, `ChangeProjectMemberRoleActionTest`, `ServiceClientAuthorizationTest`, `Illuminate\Database\Eloquent\Builder`, `UserRoleAssignmentTest`, `ChangeAutoAssignmentSettingActionTest`, `UserDetailsPageTest`, `ProjectPolicyTest`, `App\Http\Controllers\Settings\UserRoleController`, `InvitationDeliveryService`, `Illuminate\Database\Eloquent\Relations\HasMany`, `PermissionChecker`, `AccountLinkExpirationHttpTest`, `ProjectPolicyAbilitiesTest`, `NotificationDeliveryRetryServiceTest.php`, `EmailLinkExpirationAuditTest`, `PermissionMiddlewareTest`, `SettingsAuthorizationTest`?**
  _High betweenness centrality (0.038) - this node is a cross-community bridge._
- **Should `Illuminate\Support\Facades\DB` be split into smaller, more focused modules?**
  _Cohesion score 0.09023569023569024 - nodes in this community are weakly interconnected._
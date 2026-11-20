# Graph Report - TestOps  (2026-11-20)

## Corpus Check
- 342 files · ~227,155 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 28 file(s) not represented in the graph (top: (none) 20, .ini 3, .example 2)

## Summary
- 2648 nodes · 7013 edges · 218 communities (57 shown, 161 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 494 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `759990e1`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- PasswordPolicy
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
- Illuminate\Database\Eloquent\Model
- Tests\TestCase
- ChangeProjectMemberRoleActionTest
- App\Services\Notifications\InvitationDeliveryService
- ReactivateUserActionTest
- PasswordChangeTest
- AccountLinkExpirationSettings
- OAuthScopes
- AssignProjectOwnerActionTest
- .members
- UserPasswordManagementTest
- Illuminate\Database\Schema\Blueprint
- PasswordResetRequest
- AccountLinkExpirationHttpTest
- {closure#1}
- package.json
- web.php
- .store
- EnsureActiveAccountMiddlewareTest.php
- UserRoleAssignmentTest
- AuditLogPageTest
- NotificationPreferencesTest
- .retry
- ProjectOwnershipTest
- AdminProfileUpdateTest
- InvitationLifecycleTest
- NotificationDelivery.php
- UserInvitation
- NotificationDeliveryHistoryTest
- 2026_11_18_000001_add_email_verification_security_controls.php
- PermanentlyDeleteUserAction.php
- EmailVerificationRequest
- NotificationStatusTest
- ProfileUpdateTest
- NotificationDeliveryRetryServiceTest.php
- InvitationAcceptanceHttpTest
- InvitationReissueTest
- 2026_11_16_000001_create_password_reset_requests_table.php
- RevokesUserCredentialsTest
- AccountReactivatedMailable
- App\Http\Controllers\Settings\AuditLogController
- PermissionChecker
- NotificationDeliveryRetry
- .log
- PasswordPolicy
- AdminEmailChangeCancellationTest
- NotificationDelivery
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
- AuthorizationCodePkceTest
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
- App\Models\User
- 2026_11_05_193030_create_user_invitations_table.php
- Illuminate\Database\Migrations\Migration
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
- 2026_11_20_134007_create_user_notification_preferences_table.php
- UserDetailsPageTest
- OAuthScopeIssuanceTest
- layouts.settings
- BulkUserOperationsTest
- PermanentlyDeleteUserActionTest
- NotificationPreferenceEnforcementTest
- UserInvitationHttpTest
- EmailVerificationIssuanceLimitsTest
- AccountDeactivatedMailable
- AuthorizationCodePkceMiddlewareTest
- EmailVerificationLimitSettingsHttpTest
- Illuminate\Database\Eloquent\Factories\Factory
- Illuminate\Database\Eloquent\Builder
- SystemRoleChangedMailable
- 2026_11_20_134700_add_suppression_reason_to_notification_deliveries_table.php
- UserEmailAddressFactoryTest
- 2026_11_02_044815_create_roles_table.php
- InvitationLinkRequestFactory
- Illuminate\Http\RedirectResponse
- PermanentUserDeletionHttpTest
- User
- EmailLinkExpirationSetting
- 2026_11_02_074820_create_project_user_table.php
- 2026_11_05_194630_create_audit_logs_table.php
- EmailLinkExpirationAuthorizationTest
- DatabaseSeeder
- InvitationRecoveryHttpTest
- .consume
- AuthenticatedShellTest
- NotificationDeliveryRetryTest
- Laravel\Passport\Client
- AuditLoggerTest
- EmailVerificationRateLimitException
- EmailVerificationHttpTest
- App\Services\Notifications\SecurityNotificationRetryService

## God Nodes (most connected - your core abstractions)
1. `User` - 213 edges
2. `TestCase` - 114 edges
3. `Role` - 89 edges
4. `EmailVerificationService` - 81 edges
5. `Project` - 79 edges
6. `AuditLogger` - 69 edges
7. `NotificationDelivery` - 43 edges
8. `ProjectMembershipRules` - 40 edges
9. `AccountLinkExpirationSettings` - 39 edges
10. `RoleSeeder` - 39 edges

## Surprising Connections (you probably didn't know these)
- `{closure#1}()` --references--> `NotificationDelivery`  [EXTRACTED]
  tests/Feature/Services/Notifications/NotificationDeliveryRetryServiceTest.php → app/Models/NotificationDelivery.php
- `{closure#16}()` --references--> `User`  [EXTRACTED]
  tests/Feature/Settings/UserDirectoryTest.php → app/Models/User.php
- `DeactivateUserActionTest` --references--> `DeactivateUserAction`  [EXTRACTED]
  tests/Feature/Actions/DeactivateUserActionTest.php → app/Actions/DeactivateUserAction.php
- `PermanentlyDeleteUserActionTest` --references--> `PermanentlyDeleteUserAction`  [EXTRACTED]
  tests/Feature/Actions/PermanentlyDeleteUserActionTest.php → app/Actions/PermanentlyDeleteUserAction.php
- `EmailVerificationHttpTest` --references--> `EmailVerificationService`  [EXTRACTED]
  tests/Feature/Auth/EmailVerificationHttpTest.php → app/Services/Auth/EmailVerificationService.php

## Import Cycles
- None detected.

## Communities (218 total, 161 thin omitted)

### Community 0 - "PasswordPolicy"
Cohesion: 0.20
Nodes (3): ChangeOwnPasswordAction, ProfileController, PasswordPolicy

### Community 5 - "Illuminate\Foundation\Testing\RefreshDatabase"
Cohesion: 0.11
Nodes (6): Permission, Role, ExampleTest, AuditLogTest, {closure#1}(), TestCase

### Community 6 - "EmailVerificationLimitSettings"
Cohesion: 0.14
Nodes (5): SystemSettingEmailVerificationLimitController, SystemSettingAccountLinkExpirationResource, SystemSettingEmailVerificationLimitResource, EmailVerificationLimitSettings, {closure#1}()

### Community 7 - "AuditLogger"
Cohesion: 0.19
Nodes (12): AddProjectMemberAction, AssignProjectOwnerAction, ChangeProjectAutoAssignmentSettingAction, ChangeProjectMemberRoleAction, ChangeProjectOwnerAssignmentTypeAction, RemoveProjectMemberAction, TransferProjectOwnerAction, UnassignProjectOwnerAction (+4 more)

### Community 8 - "RoleSeeder"
Cohesion: 0.05
Nodes (9): ProjectAuthorizationPermissionSeeder, ProjectRoleSeeder, RoleSeeder, UserManagementPermissionSeeder, ChangeAutoAssignmentSettingActionTest, ProjectAuthorizationPermissionSeederTest, ProjectRoleSeederTest, RoleSeederTest (+1 more)

### Community 9 - "generate.js"
Cohesion: 0.06
Nodes (23): allNoBorders, ASSETS, body, BODY_FONT, bodyMargin, buildCoverR1(), bullet(), calcCoverSpacing() (+15 more)

### Community 11 - "Illuminate\Http\Request"
Cohesion: 0.07
Nodes (7): AuthenticatedSessionController, Controller, UserLifecycleController, UserRoleController, {closure#1}(), {closure#2}(), {closure#3}()

### Community 12 - "haraka"
Cohesion: 0.06
Nodes (31): context, dockerfile, driver, DELIVERY_MODE, HARAKA_SMTP_PASSWORD, HARAKA_SMTP_USER, MP_DATABASE, MP_MAX_MESSAGES (+23 more)

### Community 16 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.10
Nodes (5): EmailVerificationAttemptCounter, {closure#1}(), PasswordResetRequest, UserNotificationPreference, NotificationPreferenceChecker

### Community 17 - "Tests\TestCase"
Cohesion: 0.10
Nodes (3): {closure#2}(), {closure#1}(), {closure#1}()

### Community 19 - "App\Services\Notifications\InvitationDeliveryService"
Cohesion: 0.16
Nodes (6): DirectAccountSetupMailable, EmailChangeCancelledMailable, EmailChangeVerificationMailable, EmailVerificationMailable, PasswordResetMailable, {closure#1}()

### Community 22 - "AccountLinkExpirationSettings"
Cohesion: 0.19
Nodes (3): SystemSettingAccountLinkExpirationController, AccountLinkExpirationSettings, AccountLinkExpirationSettingsTest

### Community 23 - "OAuthScopes"
Cohesion: 0.10
Nodes (4): AppServiceProvider, {closure#1}(), ScopeRepository, PassportConfigurationTest

### Community 24 - "AssignProjectOwnerActionTest"
Cohesion: 0.09
Nodes (3): AssignProjectOwnerActionTest, UnassignProjectOwnerActionTest, ProjectPolicyTest

### Community 25 - ".members"
Cohesion: 0.06
Nodes (4): ChangeProjectOwnerAssignmentTypeActionTest, TransferProjectOwnerActionTest, ProjectMembershipHttpTest, ProjectOwnershipHttpTest

### Community 27 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.10
Nodes (12): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}() (+4 more)

### Community 30 - "{closure#1}"
Cohesion: 0.19
Nodes (8): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

### Community 31 - "package.json"
Cohesion: 0.13
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, optionalDependencies, @laravel/multiplex@^0.4.1 (+12 more)

### Community 43 - "UserInvitation"
Cohesion: 0.05
Nodes (21): AcceptInvitationAction, {closure#1}(), CancelUserInvitationAction, {closure#1}(), {closure#1}(), IssueUserInvitationAction, {closure#1}(), RecoverUserInvitationAction (+13 more)

### Community 45 - "2026_11_18_000001_add_email_verification_security_controls.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 46 - "PermanentlyDeleteUserAction.php"
Cohesion: 0.17
Nodes (5): {closure#1}(), PermanentlyDeleteUserAction, UserDeletionController, RevokesUserCredentials, UserDeletionDependencyInspector

### Community 47 - "EmailVerificationRequest"
Cohesion: 0.14
Nodes (4): CompleteAccountActivationAction, EmailVerificationController, InvitationAcceptanceController, EmailVerificationRequest

### Community 53 - "2026_11_16_000001_create_password_reset_requests_table.php"
Cohesion: 0.33
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), createOutstandingRequestUniqueIndex(), up()

### Community 56 - "AccountReactivatedMailable"
Cohesion: 0.22
Nodes (3): AccountReactivatedMailable, {closure#2}(), SecurityNotificationTest

### Community 57 - "App\Http\Controllers\Settings\AuditLogController"
Cohesion: 0.14
Nodes (7): {closure#10}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}()

### Community 58 - "PermissionChecker"
Cohesion: 0.08
Nodes (8): ProjectController, {closure#1}(), EnforceAuthorizationCodePkce, EnsureEmailLinkExpirationAccess, EnsurePermission, EnsureUserIsSuperAdmin, PermissionChecker, PermissionCheckerTest

### Community 59 - "NotificationDeliveryRetry"
Cohesion: 0.17
Nodes (5): NotificationDeliveryHistoryController, NotificationDeliveryRetry, {closure#1}(), {closure#2}(), NotificationRetryException

### Community 60 - ".log"
Cohesion: 0.24
Nodes (5): {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#1}()

### Community 61 - "PasswordPolicy"
Cohesion: 0.23
Nodes (4): ForceUserPasswordResetAction, {closure#2}(), SetUserPasswordAction, UserPasswordController

### Community 63 - "NotificationDelivery"
Cohesion: 0.11
Nodes (7): NotificationDelivery, {closure#1}(), {closure#2}(), {closure#3}(), NotificationDeliveryRecorder, {closure#1}(), NotificationDeliveryRecorderTest

### Community 67 - "PasswordResetException"
Cohesion: 0.13
Nodes (7): {closure#2}(), CompleteForcedPasswordResetAction, {closure#1}(), RequestEmailChangeAction, ForcedPasswordResetController, PasswordResetException, PasswordResetService

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
Nodes (7): AuditLog, AccountCreationPermissionSeeder, AccountLinkExpirationSettingsSeeder, SuperAdminSeeder, SystemSettingPermissionSeeder, SystemSettingSeeder, TestUserSeeder

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
Cohesion: 0.29
Nodes (3): {closure#2}(), {closure#3}(), {closure#4}()

### Community 106 - "App\Models\User"
Cohesion: 0.12
Nodes (9): {closure#1}(), CancelEmailChangeAction, {closure#1}(), {closure#1}(), UpdateUserProfileAction, UserDirectoryController, UserEmailChangeController, UserProfileController (+1 more)

### Community 112 - "NotificationDeliveryHistoryController.php"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 114 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 115 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 174 - "PermanentlyDeleteUserActionTest"
Cohesion: 0.17
Nodes (3): LifecycleConnectionGuard, PermanentlyDeleteUserActionTest, LifecycleConnectionGuardTest

### Community 187 - "Illuminate\Database\Eloquent\Builder"
Cohesion: 0.15
Nodes (16): {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+8 more)

### Community 194 - "Illuminate\Http\RedirectResponse"
Cohesion: 0.15
Nodes (8): AssignUserRoleAction, DeactivateUserAction, {closure#1}(), ReactivateUserAction, BulkUserController, {closure#2}(), {closure#3}(), AccountSecurityNotificationService

### Community 196 - "User"
Cohesion: 0.09
Nodes (4): Project, User, ProjectPolicy, {closure#4}()

### Community 197 - "EmailLinkExpirationSetting"
Cohesion: 0.12
Nodes (5): SystemSettingEmailLinkExpirationController, SystemSettingEmailLinkExpirationResource, SystemSetting, EmailLinkExpirationSetting, EmailLinkExpirationSettingTest

### Community 203 - "InvitationRecoveryHttpTest"
Cohesion: 0.25
Nodes (4): InvitationLinkMailable, {closure#1}(), InvitationRecoveryHttpTest, {closure#1}()

### Community 205 - ".consume"
Cohesion: 0.17
Nodes (6): {closure#1}(), {closure#1}(), {closure#2}(), EmailChangeVerificationController, UserEmailAddress, EmailVerificationException

### Community 211 - "Laravel\Passport\Client"
Cohesion: 0.12
Nodes (4): ServiceClientPermission, OAuthScopes, ServiceClientAuthorizer, ServiceClientPermissionTest

### Community 222 - "App\Services\Notifications\SecurityNotificationRetryService"
Cohesion: 0.18
Nodes (3): PasswordChangedMailable, SecurityNotificationRetryService, {closure#1}()

## Knowledge Gaps
- **10 isolated node(s):** `layouts.app`, `php`, `layouts.app`, `layouts.app`, `layouts.app` (+5 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 671 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **161 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `AddProjectMemberActionTest`, `ProjectReadHttpTest`, `ServiceClientAuthorizationTest`, `Illuminate\Foundation\Testing\RefreshDatabase`, `AuditLogger`, `RoleSeeder`, `UserDirectoryTest.php`, `Illuminate\Http\Request`, `RemoveProjectMemberActionTest`, `Illuminate\Database\Eloquent\Model`, `Tests\TestCase`, `ChangeProjectMemberRoleActionTest`, `ReactivateUserActionTest`, `AccountLinkExpirationSettings`, `AssignProjectOwnerActionTest`, `.members`, `AccountLinkExpirationHttpTest`, `{closure#1}`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `.retry`, `UserDetailsPageTest`, `UserInvitation`, `PermanentlyDeleteUserAction.php`, `EmailVerificationRequest`, `UserInvitationHttpTest`, `NotificationDeliveryRetryServiceTest.php`, `InvitationAcceptanceHttpTest`, `InvitationReissueTest`, `RevokesUserCredentialsTest`, `PermissionChecker`, `NotificationDeliveryRetry`, `Illuminate\Database\Eloquent\Builder`, `.log`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`, `NotificationDelivery`, `PasswordResetException`, `ProjectFactory.php`, `EmailLinkExpirationSetting`, `UserFactory`, `EmailLinkExpirationAuthorizationTest`, `EmailLinkExpirationAuditTest`, `InvitationRecoveryHttpTest`, `Illuminate\Support\Facades\DB`, `SettingsAuthorizationTest`, `Laravel\Passport\Client`, `EmailVerificationHttpTest`, `AuthorizationCodePkceTest`, `App\Models\User`?**
  _High betweenness centrality (0.232) - this node is a cross-community bridge._
- **What connects `layouts.app`, `php`, `layouts.app` to the rest of the system?**
  _10 weakly-connected nodes found - possible missing edges._
- **Should `AddProjectMemberActionTest` be split into smaller, more focused modules?**
  _Cohesion score 0.12303422756706753 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `AddProjectMemberActionTest`, `ProjectReadHttpTest`, `ServiceClientAuthorizationTest`, `RoleSeeder`, `UserDirectoryTest.php`, `EmailVerificationService`, `RemoveProjectMemberActionTest`, `Tests\TestCase`, `ChangeProjectMemberRoleActionTest`, `ReactivateUserActionTest`, `AccountLinkExpirationSettings`, `OAuthScopes`, `AssignProjectOwnerActionTest`, `.members`, `AccountLinkExpirationHttpTest`, `EnsureActiveAccountMiddlewareTest.php`, `UserRoleAssignmentTest`, `.retry`, `ProjectOwnershipTest`, `InvitationLifecycleTest`, `UserDetailsPageTest`, `UserInvitation`, `OAuthScopeIssuanceTest`, `PermanentlyDeleteUserAction.php`, `EmailVerificationRequest`, `PermanentlyDeleteUserActionTest`, `UserInvitationHttpTest`, `NotificationDeliveryRetryServiceTest.php`, `InvitationAcceptanceHttpTest`, `InvitationReissueTest`, `AuthorizationCodePkceMiddlewareTest`, `RevokesUserCredentialsTest`, `PermissionChecker`, `UserEmailAddressFactoryTest`, `NotificationDelivery`, `PermissionMiddlewareTest`, `EmailLinkExpirationSetting`, `EmailLinkExpirationAuthorizationTest`, `ProjectRelationshipTest`, `EmailLinkExpirationAuditTest`, `InvitationRecoveryHttpTest`, `Illuminate\Support\Facades\DB`, `SettingsAuthorizationTest`, `AuthenticatedShellTest`, `NotificationDeliveryRetryTest`, `Laravel\Passport\Client`, `AuditLoggerTest`, `EmailVerificationHttpTest`, `AuthorizationCodePkceTest`, `NotificationDeliveryTest`?**
  _High betweenness centrality (0.082) - this node is a cross-community bridge._
- **Should `ProjectReadHttpTest` be split into smaller, more focused modules?**
  _Cohesion score 0.1396011396011396 - nodes in this community are weakly interconnected._
- **Why does `Role` connect `Illuminate\Foundation\Testing\RefreshDatabase` to `AddProjectMemberActionTest`, `ProjectReadHttpTest`, `ServiceClientAuthorizationTest`, `AuditLogger`, `RoleSeeder`, `UserDirectoryTest.php`, `Illuminate\Http\Request`, `RemoveProjectMemberActionTest`, `Illuminate\Database\Eloquent\Model`, `Tests\TestCase`, `ChangeProjectMemberRoleActionTest`, `ReactivateUserActionTest`, `AssignProjectOwnerActionTest`, `.members`, `AccountLinkExpirationHttpTest`, `{closure#1}`, `UserRoleAssignmentTest`, `UserDetailsPageTest`, `UserInvitation`, `NotificationDeliveryRetryServiceTest.php`, `PermissionChecker`, `Illuminate\Database\Eloquent\Builder`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`, `PermissionMiddlewareTest`, `User`, `EmailLinkExpirationAuthorizationTest`, `Illuminate\Support\Facades\DB`, `Laravel\Passport\Client`?**
  _High betweenness centrality (0.032) - this node is a cross-community bridge._
- **Should `Illuminate\Foundation\Testing\RefreshDatabase` be split into smaller, more focused modules?**
  _Cohesion score 0.11184939091915837 - nodes in this community are weakly interconnected._
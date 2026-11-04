# TestOps Email Infrastructure

Local development and testing email infrastructure for TestOps: a Haraka SMTP server that accepts authenticated submissions from Laravel and either forwards them to Mailpit for inspection or delivers messages natively, orchestrated with Podman Compose.

> **Scope:** this is a **local development and testing stack**. It is not production email infrastructure.

## Architecture

| Component       | Role                                                          | Host endpoint          | Binding         |
| --------------- | ------------------------------------------------------------- | ---------------------- | --------------- |
| Haraka 3        | Authenticated SMTP submission; outbound delivery              | `127.0.0.1:2525`       | Loopback (host) |
| Mailpit v1.32   | Local email capture and inspection (SMTP + web UI)            | `127.0.0.1:8025` (UI)  | Loopback (host) |
| Podman Compose  | Container lifecycle management                                | —                      | —               |

- Haraka listens on port `2525` inside the container network, published to the host at **`127.0.0.1:2525`** only. Laravel connects here.
- Mailpit's web UI is published to the host at **`http://127.0.0.1:8025`**. Mailpit's own SMTP listener (port `1025`) is **internal to the container network** and is not published to the host.
- Both host-facing ports are bound to loopback — the stack is not reachable from other machines.

### Delivery pipeline (capture mode, the default)

```
Laravel ──SMTP over TLS──▶ Haraka (127.0.0.1:2525) ──queue/smtp_forward──▶ Mailpit ──▶ Mailpit web UI (127.0.0.1:8025)
```

Plugin chain (`haraka/config/plugins`): `tls` → `auth/flat_file` → `rcpt_to.authenticated_only` → `queue/smtp_forward`.

The local `rcpt_to.authenticated_only.js` plugin denies `RCPT` for unauthenticated connections, so **only authenticated connections may submit messages**.

## Prerequisites

- **Podman Desktop**, including the Podman CLI. This machine's `podman compose` front end is backed by Docker Compose; verify yours with `podman compose version`.
- A running Podman machine on Windows (see [Troubleshooting](#troubleshooting)).
- All Compose commands run from the `infrastructure/email` directory.

## Initial setup

Run from `infrastructure/email`.

### 1. Create the local environment file

```powershell
Copy-Item .env.example .env
```

Bash: `cp .env.example .env`.

### 2. Generate a strong SMTP password

The Haraka entrypoint validates credentials at startup and **requires `HARAKA_SMTP_PASSWORD` to be hexadecimal with at least 32 characters** (`^[a-fA-F0-9]{32,}$`). A 64-character hex secret (32 bytes of entropy) is a comfortable default.

Generate one locally — **PowerShell**, using the .NET cryptographic random generator:

```powershell
$bytes = New-Object byte[] 32
[System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
($bytes | ForEach-Object { $_.ToString('x2') }) -join ''
```

The command prints the generated secret. Copy it into `infrastructure/email/.env` as `HARAKA_SMTP_PASSWORD`. Use it only for this stack; do not reuse it anywhere else. If you change it later, recreate the containers (`podman compose up -d`) so the new value is baked in — see [Rebuild after configuration or code changes](#rebuild-after-configuration-or-code-changes). Bash equivalent: `openssl rand -hex 32` (requires OpenSSL).

The username in `.env.example`, `testops-laravel`, is a non-secret default. It may be changed to any value matching `^[A-Za-z0-9._-]+$` (validated by the entrypoint).

**Do not commit `.env` or print its contents anywhere persistent.**

### 3. Generated files (created automatically by the entrypoint)

| File                               | Purpose                                                                        |
| ---------------------------------- | ------------------------------------------------------------------------------ |
| `haraka/config/auth_flat_file.ini` | SMTP authentication configuration (PLAIN and LOGIN methods)                     |
| `haraka/config/smtp_forward.ini`   | Message delivery configuration, derived from `DELIVERY_MODE`                    |
| `haraka/config/tls_key.pem`        | Self-signed TLS private key (mode `0600`) if one does not exist                 |
| `haraka/config/tls_cert.pem`       | Matching self-signed certificate (`CN=localhost`, SAN `DNS:localhost, IP:127.0.0.1`, 825 days) |

All four are Git-ignored. The key and certificate persist on the host because `haraka/config` is bind-mounted into the container; the certificate is generated once and reused until the files are deleted.

## Startup and operations

Verified on this setup with `podman compose`. All commands run from `infrastructure/email`; Compose reads the local `.env` automatically.

### Build and start

```powershell
podman compose up -d --build
```

### Check container status

```powershell
podman compose ps
```

### View logs

```powershell
podman compose logs -f
```

Single service: `podman compose logs -f haraka` or `podman compose logs -f mailpit`.

### Restart

```powershell
podman compose restart
```

### Stop

```powershell
podman compose down
```

### Rebuild after configuration or code changes

```powershell
podman compose up -d --build
```

The entrypoint rewrites `auth_flat_file.ini` and `smtp_forward.ini` on every startup, but it generates them from the environment variables **baked into the container at creation time**. `podman compose restart` re-runs the entrypoint with the *old* variables — it does not refresh the environment. Changes to `.env` values (`HARAKA_SMTP_USER`, `HARAKA_SMTP_PASSWORD`, `DELIVERY_MODE`) therefore require **recreating the containers**:

```powershell
podman compose up -d
```

Compose recreates a service automatically when its configuration changed. To force recreation unconditionally, use `podman compose up -d --force-recreate`.

Rebuild with `--build` only after changing the `Dockerfile`, entrypoint, or plugin scripts; rebuilds alone do not refresh environment variables — combine with recreation (`up -d --build` recreates changed containers as part of `up`).

## Delivery modes

`DELIVERY_MODE` is set in `infrastructure/email/.env`. **`capture` is the default.**

| Mode      | Behavior                                                                 |
| --------- | ------------------------------------------------------------------------ |
| `capture` | Authenticated messages are forwarded to Mailpit over the internal network |
| `real`    | Authenticated messages use Haraka native outbound MX delivery             |

In capture mode, the entrypoint writes `enable_outbound=true` plus the internal Mailpit target (`host=mailpit`, `port=1025`) into `smtp_forward.ini`, so submitted messages are passed to Mailpit instead of leaving the machine. In real mode, the entrypoint writes `enable_outbound=false`, disabling forwarding so Haraka's outbound system queues and delivers messages by resolving MX records for the recipient domain.

> [!WARNING]
> **Real mode requires outbound TCP port 25 access** to remote MX hosts. Many ISPs and network firewalls block outbound port 25, so real-mode delivery may fail with connection errors even though the stack is healthy. Real mode is **not production-ready email infrastructure** — it has no SPF alignment, DKIM signing, reputation, bounce handling, or abuse controls.

## Laravel integration

Configure the root Laravel `.env` (backend defaults come from `config/mail.php`) to submit through Haraka:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=<same value as HARAKA_SMTP_USER>
MAIL_PASSWORD=<same hex secret as HARAKA_SMTP_PASSWORD>
MAIL_PEER_FINGERPRINT=<SHA-1 fingerprint of the Haraka TLS certificate, no colons>
```

Notes:

- `MAIL_USERNAME` and `MAIL_PASSWORD` must match `HARAKA_SMTP_USER` and `HARAKA_SMTP_PASSWORD` in `infrastructure/email/.env`. Substitute your locally generated values — never placeholders, never real credentials.
- `config/mail.php` sets `'require_tls' => true`, so the connection between Laravel and Haraka negotiates TLS as required.
- Only your root `.env` and the email `.env` files hold real values; both are Git-ignored.

### TLS certificate and peer fingerprint

Because the certificate is self-signed, normal certificate-authority validation cannot succeed. The stack uses **certificate pinning** instead:

1. On the first start (or whenever `haraka/config/tls_key.pem`/`tls_cert.pem` are missing), the entrypoint generates a self-signed certificate for `CN=localhost` with SAN `DNS:localhost, IP:127.0.0.1`, valid 825 days — persisted on the host through the bind mount.
2. Laravel's `config/mail.php` sets `'verify_peer' => false` — standard CA chain validation is disabled — and passes `MAIL_PEER_FINGERPRINT` to the SMTP transport as the expected certificate fingerprint.
3. TLS to Haraka succeeds only when the server presents that exact certificate.

This is **not equivalent to public CA validation**. The fingerprint pins trust to one specific local certificate; it works only because the stack is local, loopback-bound, and short-lived. If the certificate is regenerated, the pinned fingerprint changes and connections that still use the old value fail.

### Retrieving the fingerprint

There is no dedicated command. The certificate is bind-mounted to the host at `haraka/config/tls_cert.pem`, so compute the SHA-1 fingerprint from the certificate file.

Inside the container (works even without OpenSSL on the host):

```powershell
podman compose exec haraka openssl x509 -in /app/config/tls_cert.pem -noout -fingerprint -sha1
```

Output format: `sha1 Fingerprint=AA:BB:CC:...`. Use the hexadecimal value **without colons** as `MAIL_PEER_FINGERPRINT`.

If OpenSSL is available on the host, the same output can be produced directly against the bind-mounted file:

```bash
openssl x509 -in haraka/config/tls_cert.pem -noout -fingerprint -sha1
```

### Rotating the certificate

1. Stop the stack: `podman compose down`.
2. Delete `haraka/config/tls_key.pem` and `haraka/config/tls_cert.pem` from the host.
3. Start the stack: `podman compose up -d`. The entrypoint generates a fresh self-signed certificate.
4. Compute the new fingerprint and update `MAIL_PEER_FINGERPRINT` in the root `.env`. If Laravel had configuration cached, run `php artisan config:clear`.

Connections pinned to the old fingerprint will keep failing until it is updated.

## Testing

1. **Start the stack** (see [Startup and operations](#startup-and-operations)) and put the current fingerprint in the root `.env` as `MAIL_PEER_FINGERPRINT`.

2. **From the Laravel project root**, send a test message through `tinker`. Credentials are already loaded from `.env` by Laravel — you do not need to type them:

   ```powershell
   php artisan tinker --execute 'Mail::raw("TestOps email infrastructure check.", fn ($message) => $message->to("inbox@test.invalid")->subject("TestOps SMTP check"));'
   ```

   Bash syntax is identical. Do not paste credentials into the command.

3. **Verify receipt in Mailpit** at `http://127.0.0.1:8025`. The message should appear within seconds in capture mode.

The recipient `inbox@test.invalid` uses the reserved `.invalid` top-level domain (RFC 2606), which can never resolve — safe even if a test message ever reaches real-mode outbound delivery. Do not send test messages to real external addresses.

## Persistence and cleanup

`compose.yaml` declares two named volumes:

| Volume          | Used by | Contents                                        |
| --------------- | ------- | ----------------------------------------------- |
| `haraka_queue`  | Haraka  | Haraka message queue (`/app/queue`)             |
| `mailpit_data`  | Mailpit | Mailpit's captured-message database (`/data`)   |

Volumes persist across restarts and plain `podman compose down`. The bind-mounted TLS certificate also persists. Only `podman compose down -v` removes the volumes, which **destroys all captured Mailpit messages and any queued Haraka messages permanently**.

## Troubleshooting

All log/output commands run from `infrastructure/email`.

| Symptom                           | Check                                                                                      |
| --------------------------------- | ------------------------------------------------------------------------------------------ |
| Podman machine not running        | `podman machine start`, `podman machine list`. Container commands fail with connection errors. Start Podman Desktop first on Windows. |
| Container startup failures        | `podman compose ps -a` and `podman compose logs haraka`. Most common cause: `HARAKA_SMTP_PASSWORD` is not hexadecimal or is shorter than 32 characters, or `HARAKA_SMTP_USER` contains characters outside `[A-Za-z0-9._-]` — the entrypoint aborts with an explicit validation error. |
| Port conflicts                    | Only `2525` and `8025` are published to loopback. Check `netstat -ano \| findstr :2525` (PowerShell) or `ss -ltn \| grep -E '2525\|8025'` (bash). |
| SMTP authentication failures      | Root `.env` `MAIL_USERNAME`/`MAIL_PASSWORD` must exactly match `HARAKA_SMTP_USER`/`HARAKA_SMTP_PASSWORD`. The entrypoint regenerates `auth_flat_file.ini` from the environment each container start, but the environment itself is baked in at container creation — after changing credentials, run `podman compose up -d` to recreate the containers (a plain `podman compose restart` keeps the old values). If you changed the Laravel side only, restart nothing there — Laravel reads `.env` per request in local development, or run `php artisan config:clear` if configuration was cached. Laravel reports authentication failures as "Failed to authenticate on SMTP server". Inspect `podman compose logs -f haraka` during the attempt. |
| TLS fingerprint mismatch          | Regenerate or rotate the certificate, then re-read the current fingerprint (see [Retrieving the fingerprint](#retrieving-the-fingerprint)) and update `MAIL_PEER_FINGERPRINT` in the root `.env`. Connections pinned to a stale fingerprint will keep failing until it is updated. |
| Laravel unable to connect         | Email stack up (`podman compose ps`)? Correct root `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=2525`)? Port open: `Test-NetConnection 127.0.0.1 -Port 2525` (PowerShell) or `nc -vz 127.0.0.1 2525` (bash). Haraka advertises STARTTLS via the `tls` plugin; Laravel sends `require_tls`. |
| Emails not appearing in Mailpit   | Check the current `DELIVERY_MODE` in `infrastructure/email/.env` — in `real` mode messages never reach Mailpit. Set `capture` and recreate the containers with `podman compose up -d` (a plain `restart` applies no change). Check `podman compose logs haraka` for a connection error to the internal Mailpit SMTP target (port `1025`). |
| Outbound delivery failure in real mode | Outbound TCP 25 is likely blocked by your ISP or local firewall, or the remote host rejected the message. Inspect `podman compose logs haraka` and the Haraka queue contents. Real mode has no bounce handling, so messages can stay queued silently. |

## Security boundary

This stack exists to test outbound email behavior locally. Its security properties are scoped to that purpose:

- Both host-facing ports are bound to loopback — the stack is not reachable from other machines.
- Only authenticated connections may submit messages; unauthenticated `RCPT` requests are denied.
- The connection is TLS-encrypted via a self-signed certificate, pinned by SHA-1 fingerprint. This is **not equivalent to public CA validation**.
- The configuration has no rate limiting, abuse controls, monitoring, or review history.

**This is not production email hosting.** Production deployment would require a separate architecture with proper DNS, SPF, DKIM, DMARC, reverse DNS (PTR), monitoring, abuse controls, and managed credential handling — none of which are provided here.

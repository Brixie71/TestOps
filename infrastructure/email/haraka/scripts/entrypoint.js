'use strict';

const fs = require('node:fs');
const { spawn, spawnSync } = require('node:child_process');

const mode = process.env.DELIVERY_MODE || 'capture';
const username = process.env.HARAKA_SMTP_USER || '';
const password = process.env.HARAKA_SMTP_PASSWORD || '';

if (!['capture', 'real'].includes(mode)) {
    throw new Error('DELIVERY_MODE must be capture or real');
}

if (!/^[A-Za-z0-9._-]+$/.test(username)) {
    throw new Error('HARAKA_SMTP_USER is missing or invalid');
}

if (!/^[a-fA-F0-9]{32,}$/.test(password)) {
    throw new Error(
        'HARAKA_SMTP_PASSWORD must be a hexadecimal secret of at least 32 characters'
    );
}

const configDir = '/app/config';

fs.mkdirSync(configDir, { recursive: true });

// Generate a local self-signed certificate if the certificate pair is absent.
const keyPath = `${configDir}/tls_key.pem`;
const certPath = `${configDir}/tls_cert.pem`;

if (!fs.existsSync(keyPath) || !fs.existsSync(certPath)) {
    console.log('Generating local SMTP TLS certificate');

    const result = spawnSync('openssl', [
        'req',
        '-x509',
        '-nodes',
        '-days', '825',
        '-newkey', 'rsa:2048',
        '-keyout', keyPath,
        '-out', certPath,
        '-subj', '/CN=localhost',
        '-addext', 'subjectAltName=DNS:localhost,IP:127.0.0.1'
    ], {
        stdio: 'inherit'
    });

    if (result.error) {
        throw result.error;
    }

    if (result.status !== 0) {
        throw new Error('Failed to generate SMTP TLS certificate');
    }
}

fs.chmodSync(keyPath, 0o600);

// Generate SMTP authentication configuration.
const authConfig = [
    '[core]',
    'methods=PLAIN,LOGIN',
    'constrain_sender=false',
    '',
    '[users]',
    `${username}=${password}`,
    ''
].join('\n');

fs.writeFileSync(
    `${configDir}/auth_flat_file.ini`,
    authConfig,
    { mode: 0o600 }
);

// Configure message delivery based on the selected mode.
const forwardConfig = mode === 'capture'
    ? 'enable_outbound=true\nhost=mailpit\nport=1025\n'
    : 'enable_outbound=false\n';

fs.writeFileSync(
    `${configDir}/smtp_forward.ini`,
    forwardConfig,
    { mode: 0o600 }
);

console.log(`Starting Haraka in ${mode} mode`);

const haraka = spawn('haraka', ['-c', '/app'], {
    stdio: 'inherit'
});

// Forward container termination signals to Haraka.
for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
        if (!haraka.killed) {
            haraka.kill(signal);
        }
    });
}

haraka.on('error', (error) => {
    console.error('Unable to start Haraka:', error);
    process.exitCode = 1;
});

haraka.on('exit', (code, signal) => {
    process.exitCode = code ?? (signal ? 1 : 0);
});

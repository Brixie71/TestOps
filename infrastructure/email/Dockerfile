FROM node:26-bookworm-slim

WORKDIR /app

# Install OpenSSL for local SMTP certificate generation.
RUN apt-get update \
    && apt-get install -y --no-install-recommends openssl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Install Haraka.
RUN npm install --global Haraka@3 \
    && mkdir -p /app/config /app/plugins /app/queue \
    && cp -a /usr/local/lib/node_modules/Haraka/docs /app/docs

COPY haraka/scripts/entrypoint.js /usr/local/bin/entrypoint.js

EXPOSE 2525

ENTRYPOINT ["node", "/usr/local/bin/entrypoint.js"]

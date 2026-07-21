# Local Setup (Fedora)

## System Packages

```bash
sudo dnf install -y \
  php \
  php-cli \
  php-common \
  php-curl \
  php-mbstring \
  php-xml \
  php-zip \
  php-bcmath \
  php-intl \
  php-readline \
  php-tokenizer \
  php-pgsql \
  php-pdo \
  php-sqlite3 \
  postgresql-server \
  postgresql \
  poppler-utils \
  nodejs \
  npm \
  git \
  unzip \
  curl
```

`poppler-utils` provides `pdftoppm` and `pdfinfo`, which the floorplan
renderer shells out to when processing uploaded estimate PDFs. Without it,
estimate extraction succeeds but floorplan rendering fails with a "render
failed" status.

If your Fedora version doesn't ship PHP 8.4, enable the Remi repository:

```bash
sudo dnf install -y https://rpms.remirepo.net/fedora/remi-release-$(rpm -E %fedora).rpm
sudo dnf module reset php
sudo dnf module enable php:remi-8.4
sudo dnf install -y php php-cli php-common php-curl php-mbstring php-xml \
  php-zip php-bcmath php-intl php-readline php-tokenizer php-pgsql php-pdo php-sqlite3
```

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Node.js (if the distro version is too old)

The project uses Vite 7 which needs Node 18+. If your distro ships an older version, use NodeSource or nvm:

```bash
# Option A: nvm
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
nvm install 22
nvm use 22

# Option B: NodeSource
curl -fsSL https://rpm.nodesource.com/setup_22.x | sudo -E bash -
sudo dnf install -y nodejs
```

## PostgreSQL Setup

Initialize and start the PostgreSQL service:

```bash
sudo postgresql-setup --initdb
sudo systemctl enable --now postgresql
```

Production uses PostgreSQL. Create a database and user:

```bash
sudo -u postgres psql <<SQL
CREATE USER whitevan WITH PASSWORD 'whitevan';
CREATE DATABASE whitevan OWNER whitevan;
SQL
```

Fedora's default `pg_hba.conf` uses `ident` auth for local connections. Change the local IPv4 method to `md5` or `scram-sha-256` so password auth works:

```bash
sudo sed -i 's/^\(host.*127.0.0.1.*\)ident/\1md5/' /var/lib/pgsql/data/pg_hba.conf
sudo systemctl restart postgresql
```

## Environment

```bash
cp .env.example .env
```

Edit `.env` with your database and Google OAuth credentials:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=whitevan
DB_USERNAME=whitevan
DB_PASSWORD=whitevan

GOOGLE_OAUTH_CLIENT_ID=your-client-id
GOOGLE_OAUTH_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/oauth/callback/google
```

Google OAuth credentials come from the [Google Cloud Console](https://console.cloud.google.com/apis/credentials). Create an OAuth 2.0 Client ID and add `http://localhost:8000/oauth/callback/google` as an authorized redirect URI.

## Install & Build

```bash
composer run setup
```

This runs `composer install`, copies `.env.example` (if `.env` doesn't exist), generates an app key, runs migrations, installs npm packages, and builds frontend assets.

## Run

```bash
composer run dev
```

Starts the PHP dev server, queue listener, and Vite dev server concurrently. The app will be at `http://localhost:8000`.

## Tests

Tests use in-memory SQLite (no PostgreSQL needed):

```bash
composer run test
```

## Linting

```bash
./vendor/bin/pint
```

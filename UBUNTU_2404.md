# Local Setup (Ubuntu 24.04 LTS)

Ubuntu 24.04 "Noble" ships PHP 8.3, but this project needs PHP 8.4. The steps
below add Ondřej Surý's PPA to get 8.4 packages.

## System Packages

Add the PHP 8.4 PPA first, then install everything:

```bash
sudo apt update
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

sudo apt install -y \
  php8.4 \
  php8.4-cli \
  php8.4-common \
  php8.4-curl \
  php8.4-mbstring \
  php8.4-xml \
  php8.4-zip \
  php8.4-bcmath \
  php8.4-intl \
  php8.4-readline \
  php8.4-pgsql \
  php8.4-sqlite3 \
  postgresql \
  postgresql-contrib \
  git \
  unzip \
  curl
```

The `tokenizer` and `pdo` extensions are bundled into the core `php8.4` package
on Ubuntu, so there's no separate package to install for them.

Make PHP 8.4 the default CLI if you have multiple versions installed:

```bash
sudo update-alternatives --set php /usr/bin/php8.4
php -v   # confirm 8.4.x
```

### Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Node.js

The project uses Vite 7, which needs Node 18+. Ubuntu 24.04's `nodejs` package is
recent enough, but NodeSource or nvm give you a specific version and easier
upgrades:

```bash
# Option A: nvm
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash
nvm install 22
nvm use 22

# Option B: NodeSource
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

## PostgreSQL Setup

Ubuntu starts and enables PostgreSQL automatically on install. Confirm it's
running:

```bash
sudo systemctl enable --now postgresql
systemctl status postgresql
```

Production uses PostgreSQL. Create a database and user:

```bash
sudo -u postgres psql <<SQL
CREATE USER whitevan WITH PASSWORD 'whitevan';
CREATE DATABASE whitevan OWNER whitevan;
SQL
```

Ubuntu's default `pg_hba.conf` uses `peer` auth for local socket connections and
`scram-sha-256` for host connections, so password auth over `127.0.0.1` works out
of the box. If you hit an auth error, verify the host rule and reload. The config
lives under `/etc/postgresql/<version>/main/` (16 on 24.04):

```bash
sudo sed -i 's/^\(host.*127.0.0.1\/32.*\)\(md5\|peer\|ident\)/\1scram-sha-256/' \
  /etc/postgresql/16/main/pg_hba.conf
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

Google OAuth credentials come from the [Google Cloud Console](https://console.cloud.google.com/apis/credentials).
Create an OAuth 2.0 Client ID and add `http://localhost:8000/oauth/callback/google`
as an authorized redirect URI.

## Install & Build

```bash
composer run setup
```

This runs `composer install`, copies `.env.example` (if `.env` doesn't exist),
generates an app key, runs migrations, installs npm packages, and builds frontend
assets.

## Run

```bash
composer run dev
```

Starts the PHP dev server, queue listener, and Vite dev server concurrently. The
app will be at `http://localhost:8000`.

## Tests

Tests use in-memory SQLite (no PostgreSQL needed):

```bash
composer run test
```

## Linting

```bash
./vendor/bin/pint
```

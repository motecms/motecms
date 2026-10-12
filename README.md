# MoteCMS

MoteCMS is an experimental, ultra-lightweight, server-rendered CMS for low-bandwidth, high-latency and delay-tolerant networks. This first MVP is deliberately small: plain PHP, plain HTML, one CSS file, no database, no Composer packages and no required JavaScript.

MoteCMS is transport-agnostic. It does not implement LoRa, LoRaWAN, Reticulum, IP networking or radio transport; it only keeps the web application and generated payloads small.

## Design principles

- Server-rendered by default.
- Dependency-free core using PHP 8.2 or newer.
- No public JavaScript, external resources, web fonts, images or CDNs.
- Small, readable files over framework architecture.
- Security and accessibility are not sacrificed for byte savings.
- Every feature should justify its byte cost.

## Features

- Public website with header, sidebar, body and footer templates.
- Responsive CSS Grid layout that works without JavaScript.
- File-based pages stored as UTF-8 Markdown-like `.md` files.
- Draft and published states.
- Header, sidebar or hidden navigation placement.
- Configurable menu ordering.
- Password-protected `/admin/` interface for create, edit and delete.
- Multi-user support with **admin** and **editor** roles.
- PHP session authentication with `password_hash()` / `password_verify()`.
- CSRF protection for all write, login and logout actions.
- Activity logging for admin actions (login, page changes, user management).
- ETag and Last-Modified headers for public pages.
- Restrictive security headers.

## Requirements

- PHP 8.2 or newer.
- A web server that can serve `public/index.php` and `public/admin/index.php`.
- No Composer install step is required.

## Quick start

### Multi-user mode (default)
```sh
cp config/config.example.php config/config.php
# Edit config/users.php to set your admin password
php -S 127.0.0.1:8080 -t public
```

### Single-user mode (legacy)
```sh
cp config/config.example.php config/config.php
# Set multi_user_mode to false in config/config.php
php bin/hash-password.php 'choose a strong password'
export MOTECMS_ADMIN_PASSWORD_HASH='paste_generated_hash_here'
php -S 127.0.0.1:8080 -t public
```

Visit `http://127.0.0.1:8080/index.php` for the public site and `http://127.0.0.1:8080/admin/` for administration.

## Content file format

Pages live in `content/pages` and use simple front matter:

```md
---
title: Home
slug: home
status: published
menu: sidebar
order: 10
---

# Welcome to MoteCMS

This page is rendered on the server.
```

Supported fields are `title`, `slug`, `status` (`draft` or `published`), `menu` (`header`, `sidebar` or `none`) and integer `order`. Slugs must match `[a-z0-9][a-z0-9-]{0,63}`. Public URLs use rewrite-free query strings such as `/index.php?page=about`; clean URLs may be added later.

## Supported markup

The renderer intentionally supports only a safe subset:

- headings `#`, `##` and `###`;
- paragraphs;
- unordered lists using `- item`;
- fenced code blocks with triple backticks, including ASCII art;
- inline code with backticks;
- `**bold**` and `*emphasis*`;
- links `[label](url)` for `http`, `https` and `mailto` only.

Raw HTML is escaped. Unsafe link schemes such as `javascript:` are not emitted as links.

## Directory structure

```text
bin/                 password hash helper
config/              non-secret configuration example and users file
config/users.php     user accounts (created on first setup)
content/pages/       file-based page content
public/              web root
public/admin/        administration interface
public/assets/       single stylesheet
public/cache/        page cache (created automatically)
src/                 small PHP classes and bootstrap
templates/           public layout templates
tests/               dependency-free test runner
```

## Low-bandwidth choices

The public interface has one CSS request, no JavaScript, no external network requests, compact semantic HTML, ETag and Last-Modified support, and no polling or auto-refresh behavior.

## Security notes

Set `MOTECMS_ADMIN_PASSWORD_HASH` in the environment; never commit a real password or hash. Sessions use HttpOnly and SameSite=Strict cookies, with Secure enabled when HTTPS is detected. The admin interface protects mutations with CSRF tokens. Content files are addressed only through validated slugs and written via temporary files plus atomic rename.

Multi-user mode is enabled by default and uses a `config/users.php` file. To use single-user mode, set `multi_user_mode` to `false` in `config/config.php` and use the `MOTECMS_ADMIN_PASSWORD_HASH` environment variable as before.

## Limitations

- No database, media library, themes or plugins.
- No complete Markdown implementation.
- No clean URLs by default.
- No conflict resolution for simultaneous edits beyond file locking during writes.
- User roles are limited to **admin** and **editor** (no custom roles).

## Roadmap

- Optional clean URL documentation.
- Import/export helpers.
- More tests around deployment environments.
- Optional transport packaging examples while keeping the core transport-agnostic.
- User profile management (password change, etc.).
- Log export and rotation tools.

## License

MoteCMS is licensed under the GNU Affero General Public License version 3.0. In summary, you may use, study, modify and share the software, but network-accessible modified versions must also provide corresponding source under the AGPL-3.0 terms. See `LICENSE` for the full license text.

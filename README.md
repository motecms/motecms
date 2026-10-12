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

For a quick setup, point the web server document root at the `public/` directory. The front controllers are then reachable directly: `/index.php` for the public site and `/admin/` for the administration interface. Everything else (config, content, templates) stays outside the document root.

### Apache 2 example

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/motecms/public

    <Directory /var/www/motecms/public>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>

    <Directory /var/www/motecms/public/admin>
        Require all granted
    </Directory>
</VirtualHost>
```

Save it as `/etc/apache2/sites-available/motecms.conf`, then enable it and reload:

```sh
a2ensite motecms.conf
systemctl reload apache2
```

### Nginx example

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/motecms/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

Save it as `/etc/nginx/sites-available/motecms.conf`, then enable it and reload:

```sh
ln -s /etc/nginx/sites-available/motecms.conf /etc/nginx/sites-enabled/
systemctl reload nginx
```

Adjust the `fastcgi_pass` socket to match your PHP-FPM version. Both examples assume the application lives in `/var/www/motecms`.

## Quick start

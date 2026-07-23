# PHP 8.2 + Apache (mod_php). Adds the mysqli extension the app needs.
FROM php:8.2-apache

# mod_headers lets us send no-store on the attacker PoC pages (see below).
RUN docker-php-ext-install mysqli && a2enmod rewrite headers

# Serve the app under /crypto-tracker/ so all existing URLs (and the attacker
# CSRF pages, which hardcode http://localhost/crypto-tracker/...) work verbatim.
COPY crypto-tracker/ /var/www/html/crypto-tracker/

# Stop the browser caching the attacker PoC pages (stale-exploit trap).
COPY apache-attacker.conf /etc/apache2/conf-enabled/attacker.conf

# Optional convenience: redirect the site root to the app.
RUN printf '<?php header("Location: /crypto-tracker/"); ' > /var/www/html/index.php

RUN chown -R www-data:www-data /var/www/html

FROM php:8.3-apache

# PostgreSQL driver (needed to talk to Supabase)
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Never serve hidden files (.env, .git, ...) over the web
RUN printf '<FilesMatch "^\\.">\n    Require all denied\n</FilesMatch>\n' \
    > /etc/apache2/conf-available/block-dotfiles.conf \
    && a2enconf block-dotfiles

# Site lives at the web root, so BASE_URL must be "/" (set it in Render)
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
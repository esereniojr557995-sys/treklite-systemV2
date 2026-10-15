FROM php:8.4-apache

# System packages + PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql zip mbstring xml gd exif \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

# PHP's default upload limit is 2 MB, which is too small for phone photos and for backup files that now contain the photos.
RUN printf 'upload_max_filesize=100M\npost_max_size=110M\nmemory_limit=256M\n' > /usr/local/etc/php/conf.d/treklite-uploads.ini

# Silence the "ServerName" warning
RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
&& a2enconf servername

# Listen on Render's port 10000 and serve Laravel's public folder.
# This writes the whole virtual host explicitly instead of patching it with sed.
RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
&& printf '<VirtualHost *:10000>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
        DirectoryIndex index.php\n\
    </Directory>\n\
    ErrorLog /proc/self/fd/2\n\
    CustomLog /proc/self/fd/1 combined\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

# Node.js (only to build Vite assets)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
&& apt-get install -y nodejs

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN npm install \
&& npm run build \
&& rm -rf node_modules

RUN php artisan storage:link || true

# Make sure Apache (www-data) can read everything and write where Laravel needs to
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache public/uploads \
&& chown -R www-data:www-data /var/www/html \
&& find /var/www/html -type d -exec chmod 755 {} + \
&& find /var/www/html -type f -exec chmod 644 {} + \
&& chmod -R 775 storage bootstrap/cache public/uploads

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
&& chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 10000

CMD ["docker-entrypoint.sh"]
# Stage 1: Build Frontend Assets
FROM node:20-alpine AS node_builder
WORKDIR /app
COPY package*.json ./
RUN npm install --legacy-peer-deps
COPY . .
RUN npm run build

# Stage 2: PHP Application
FROM php:8.4-fpm-alpine
WORKDIR /var/www/html

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite-dev \
    libzip-dev \
    oniguruma-dev

RUN docker-php-ext-install pdo pdo_sqlite pdo_mysql bcmath gd zip mbstring

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .
# Copy compiled frontend from node_builder
COPY --from=node_builder /app/public/build ./public/build

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create empty SQLite database and set correct permissions
RUN touch /var/www/html/database/database.sqlite && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

EXPOSE 9000
CMD ["php-fpm"]

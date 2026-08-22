FROM php:8.3-fpm
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev \
    && docker-php-ext-install pdo_mysql zip bcmath gd \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
# Node.js for Tailwind/Vite
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && apt-get install -y nodejs
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

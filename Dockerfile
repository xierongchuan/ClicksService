# ---------------- STAGE: build ----------------
FROM php:8.4.12-fpm AS build

# Устанавливаем системные зависимости, необходимые для расширений PHP
# git, unzip - для Composer
# librabbitmq-dev - для расширения amqp
RUN apt-get update && apt-get install -y \
  git \
  unzip \
  librabbitmq-dev \
  && rm -rf /var/lib/apt/lists/*

# Устанавливаем расширение PHP для работы с RabbitMQ
RUN pecl install amqp \
  && docker-php-ext-enable amqp

# Скачиваем установщик Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer \
  && chmod +x /usr/bin/composer

# Устанавливаем рабочую директорию
WORKDIR /var/www/html

# Копируем кастомную конфигурацию PHP, включая OPcache
COPY ./docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Копируем файлы приложения
COPY . .

# Устанавливаем зависимости Composer
RUN composer install --optimize-autoloader --no-dev

# Меняем владельца файлов на www-data, чтобы FPM мог писать в логи и кэш
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Копирование composer файлы и установка зависимостей
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --prefer-dist

# ---------------- STAGE: runtime ----------------
FROM php:8.4.12-fpm AS runner

WORKDIR /var/www/html

# Копируем из build стадии всё что нужно для запуска
COPY --from=build /var/www/html /var/www/html

# Запуск
EXPOSE 9000

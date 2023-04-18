FROM php:8.0-cli

RUN apt-get update && \
    apt-get install -y \
        unzip \
        git \
        libzip-dev \
        librabbitmq-dev && \
    docker-php-ext-install bcmath sockets zip && \
    docker-php-ext-enable zip && \
    pecl install amqp && \
    docker-php-ext-enable amqp

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
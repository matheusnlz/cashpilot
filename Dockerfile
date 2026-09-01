FROM php:8.3-apache-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    CASHPILOT_ENV=production \
    CASHPILOT_PYTHON_PATH=/opt/cashpilot-venv/bin/python

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        python3 \
        python3-venv \
        python3-pip \
        libonig-dev \
        libcurl4-openssl-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring curl gd \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY python/requirements.txt /tmp/cashpilot-requirements.txt

RUN python3 -m venv /opt/cashpilot-venv \
    && /opt/cashpilot-venv/bin/pip install --no-cache-dir -r /tmp/cashpilot-requirements.txt

COPY . /var/www/html/

COPY docker/apache-cashpilot.conf /etc/apache2/conf-available/cashpilot.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/cashpilot.ini
COPY docker/entrypoint.sh /usr/local/bin/cashpilot-entrypoint

RUN a2enconf cashpilot \
    && chmod +x /usr/local/bin/cashpilot-entrypoint \
    && chown -R www-data:www-data /var/www/html

EXPOSE 8080

ENTRYPOINT ["cashpilot-entrypoint"]

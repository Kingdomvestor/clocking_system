FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Write a proper vhost that accepts POST and trusts proxy headers
RUN cat > /etc/apache2/sites-available/000-default.conf << 'EOF'
Listen 10000

<VirtualHost *:10000>
    ServerName soteria-clocking-system.onrender.com
    DocumentRoot /var/www/html

    SetEnvIf X-Forwarded-Proto https HTTPS=on

    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted

        # Explicitly allow all HTTP methods including POST
        <LimitExcept GET POST PUT DELETE PATCH OPTIONS HEAD>
            Require all denied
        </LimitExcept>
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

RUN sed -i 's/Listen 80//' /etc/apache2/ports.conf \
    && sed -i 's/Listen 443//' /etc/apache2/ports.conf || true

RUN a2enmod rewrite headers

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000

CMD ["apache2-foreground"]

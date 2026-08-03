FROM reallyenglish/php:5.3-apache-0

# Compile pdo_mysql from the PHP source tree bundled with the base image.
# The base image provides docker-php-ext-install, phpize, mysql_config,
# and /usr/src/php/ext/pdo_mysql.
# Do NOT use apt-get install php5-mysql — Debian Jessie is EOL (404 repos),
# and its php5-mysql package targets a different PHP ABI than the base image's
# custom PHP 5.3 build.
RUN docker-php-ext-install pdo_mysql

# The base image already configures PHP file handling via:
#   <FilesMatch \.php$>
#       SetHandler application/x-httpd-php
#   </FilesMatch>
# in /etc/apache2/apache2.conf. No AddHandler/AddType directives are needed
# (those are CGI-style directives that do not apply to php5_module).

# Enable mod_rewrite — required by .htaccess rules (vendor blocking, URL routing)
RUN a2enmod rewrite

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/sites-available/default 2>/dev/null; \
    sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf 2>/dev/null; \
    echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# Create writable runtime directories
RUN mkdir -p /var/www/html/uploads /var/www/html/logs && \
    chown -R www-data:www-data /var/www/html/uploads /var/www/html/logs && \
    chmod 755 /var/www/html/uploads /var/www/html/logs

# Copy backend PHP files
COPY backend/procesar-envio.php /var/www/html/
COPY backend/registrations.php /var/www/html/
COPY backend/vendor/ /var/www/html/vendor/
COPY backend/.htaccess /var/www/html/
COPY backend/config.php /var/www/html/

# Copy frontend static files
COPY frontend/index.html frontend/styles.css /var/www/html/
COPY frontend/js/ /var/www/html/js/
COPY frontend/assets/ /var/www/html/assets/

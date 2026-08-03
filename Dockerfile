FROM reallyenglish/php:5.3-apache-0

# Install pdo_mysql / php5-mysql extension for MariaDB connectivity
RUN apt-get update && \
    apt-get install -y --no-install-recommends php5-mysql && \
    rm -rf /var/lib/apt/lists/*

# Fix missing PHP handler config
RUN echo 'AddHandler php5-script .php' > /etc/apache2/conf-available/php5.conf && \
    echo 'AddType text/html .php' >> /etc/apache2/conf-available/php5.conf && \
    a2enconf php5

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
COPY backend/vendor/ /var/www/html/vendor/
COPY backend/.htaccess /var/www/html/
COPY backend/config.php /var/www/html/

# Copy frontend static files
COPY frontend/index.html frontend/styles.css /var/www/html/
COPY frontend/js/ /var/www/html/js/
COPY frontend/assets/ /var/www/html/assets/

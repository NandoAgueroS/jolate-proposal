FROM reallyenglish/php:5.3-apache-0

# Fix missing PHP handler config
RUN echo 'AddHandler php5-script .php' > /etc/apache2/conf-available/php5.conf && \
    echo 'AddType text/html .php' >> /etc/apache2/conf-available/php5.conf && \
    a2enconf php5

# Enable mod_rewrite — required by .htaccess rules
RUN a2enmod rewrite

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/sites-available/default 2>/dev/null; \
    sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf 2>/dev/null; \
    echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# Create writable runtime directories
RUN mkdir -p /var/www/html/backend/uploads /var/www/html/backend/logs /var/www/html/backend/submissions && \
    chown -R www-data:www-data /var/www/html/backend/uploads /var/www/html/backend/logs /var/www/html/backend/submissions && \
    chmod 755 /var/www/html/backend/uploads /var/www/html/backend/logs /var/www/html/backend/submissions

# Copy backend PHP files (explicitly to exclude .env)
COPY backend/procesar-envio.php /var/www/html/backend/
COPY backend/procesar-correos.php /var/www/html/backend/
COPY backend/vendor/ /var/www/html/backend/vendor/
COPY backend/.htaccess /var/www/html/backend/
COPY backend/config.php /var/www/html/backend/

# Copy frontend static files
COPY index.html main.js config.js i18n.js styles.css /var/www/html/
COPY assets/ /var/www/html/assets/

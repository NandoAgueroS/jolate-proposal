FROM reallyenglish/php:5.3-apache-0

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

COPY . /var/www/html/
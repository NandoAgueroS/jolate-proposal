FROM php:8.3-apache

# Enable mod_rewrite — required by .htaccess rules (vendor blocking, URL routing)
RUN a2enmod rewrite

# Install pdo_mysql (not bundled by default in php:8.3-apache)
RUN docker-php-ext-install pdo_mysql

# Install cron for async email worker
RUN apt-get update && apt-get install -y cron && rm -rf /var/lib/apt/lists/*

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/sites-available/000-default.conf; \
    sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf; \
    echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# DocumentRoot = repo root (like a production vhost pointing at the site folder).
# The repo's own .htaccess handles routing frontend vs backend.
# Both the VirtualHost and the global config need updating — apache2.conf has its
# own DocumentRoot that overrides the site-level one.
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/jolate-proposal|' /etc/apache2/sites-available/000-default.conf; \
    sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/jolate-proposal|' /etc/apache2/apache2.conf

# Copy entire repository structure into jolate-proposal/
COPY . /var/www/html/jolate-proposal/

# Set writable permissions on runtime directories.
# Git tracks directory existence via .gitkeep but cannot store
# ownership or mode — both Docker and production need this step.
RUN /var/www/html/jolate-proposal/bin/setup-runtime.sh /var/www/html/jolate-proposal

# Cron configuration for email worker
COPY docker/crontab /etc/cron.d/jolate
RUN chmod 0644 /etc/cron.d/jolate && crontab -u www-data /etc/cron.d/jolate
RUN touch /var/log/jolate-cron.log && chown www-data:www-data /var/log/jolate-cron.log

# Entrypoint starts cron + Apache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]

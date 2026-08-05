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

# Expose backend/uploads/ at a clean /uploads/ URL (used in email download links)
COPY docker/000-uploads-alias.conf /etc/apache2/conf-enabled/000-uploads-alias.conf

# Set writable permissions on runtime directories.
# Git tracks directory existence via .gitkeep but cannot store
# ownership or mode — both Docker and production need this step.
RUN /var/www/html/jolate-proposal/bin/setup-runtime.sh /var/www/html/jolate-proposal

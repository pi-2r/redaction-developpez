FROM php:8.2-apache-bullseye

# Installation des dépendances système nécessaires
# libonig-dev pour mbstring
# libzip-dev pour zip
# libxslt1-dev pour xsl
# libpng-dev pour gd
# wkhtmltopdf pour la génération PDF
# (Calibre / ebook-convert n'est pas installé : trop volumineux. Sans lui,
#  les exports ePub/Mobi/AZW sont simplement ignorés à la publication.)
RUN apt-get update && apt-get install -y \
    libonig-dev \
    libzip-dev \
    libxslt1-dev \
    libpng-dev \
    wkhtmltopdf \
    unzip \
    && docker-php-ext-install mbstring zip xsl gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Activation du module rewrite pour Apache
RUN a2enmod rewrite

# Autoriser les .htaccess (désactivés par défaut dans l'image) : le CMS en
# génère pour protéger les sources, meta.json et admin/data.
RUN printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/cms-htaccess.conf \
    && a2enconf cms-htaccess

# Fichier des comptes hors de la racine web (monté par docker-compose)
RUN mkdir -p /var/www/private && chown www-data:www-data /var/www/private
ENV CMSXML_PASSWORD_FILE=/var/www/private/passwords.txt

# Configuration du répertoire de travail
WORKDIR /var/www/html

# Copie des fichiers du projet (voir .dockerignore : ni .git, ni mots de passe)
COPY . /var/www/html/

# Permissions
# Apache tourne sous l'utilisateur www-data
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Le port 80 est exposé par défaut par l'image php:apache
EXPOSE 80

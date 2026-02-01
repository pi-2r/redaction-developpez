FROM php:8.2-apache

# Installation des dépendances système nécessaires
# libonig-dev pour mbstring
# libzip-dev pour zip
# libxslt1-dev pour xsl
# libpng-dev pour gd
# wkhtmltopdf pour la génération PDF
RUN apt-get update && apt-get install -y \
    libonig-dev \
    libzip-dev \
    libxslt1-dev \
    libpng-dev \
    wkhtmltopdf \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring zip xsl gd intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Activation du module rewrite pour Apache
RUN a2enmod rewrite

# Configuration du répertoire de travail
WORKDIR /var/www/html

# Copie des fichiers du projet
COPY . /var/www/html/

# Permissions
# Apache tourne sous l'utilisateur www-data
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Le port 80 est exposé par défaut par l'image php:apache
EXPOSE 80

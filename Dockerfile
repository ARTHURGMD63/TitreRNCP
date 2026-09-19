# ============================================================
#  StudentLink — image de production
#
#  Ce qui remplaçait cette image jusqu'ici :
#
#      FROM php:8.3-cli
#      CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app"]
#
#  `php -S` est le serveur de développement intégré. Il traite UNE requête
#  à la fois — il ne crée des processus de travail que si la variable
#  PHP_CLI_SERVER_WORKERS est définie, ce qu'elle n'était pas — et la
#  documentation de PHP écrit noir sur blanc qu'il n'est pas destiné à la
#  production. Le plafond n'était donc pas de mille utilisateurs simultanés
#  mais d'une requête simultanée : tout le reste attendait son tour.
#
#  Cette image corrige trois choses d'un coup :
#    1. Apache avec mod_php, qui sert réellement en parallèle ;
#    2. OPcache, absent, donc tout le code PHP était recompilé à chaque
#       requête ;
#    3. GD, absente elle aussi — includes/uploads.php redimensionne les
#       photos avec imagecreatetruecolor(), qui n'existait pas dans le
#       conteneur : les avatars partaient en pleine résolution.
# ============================================================
FROM php:8.3-apache

# ─── Extensions ─────────────────────────────────────────────────────────────
#
#  Les paquets -dev ne sont PAS désinstallés ensuite, et c'est délibéré.
#  La première version de ce fichier se terminait par :
#
#      apt-get purge -y --auto-remove libjpeg62-turbo-dev libpng-dev …
#
#  L'image se construisait sans une erreur, et GD refusait de se charger au
#  démarrage : « libpng16.so.16: cannot open shared object file ». En
#  emportant les paquets -dev, --auto-remove emportait aussi les
#  bibliothèques d'exécution dont GD dépend — celles-ci n'étaient installées
#  que comme dépendances, donc marquées « automatiques ».
#
#  Le résultat était exactement la panne que cette image devait corriger :
#  une extension absente, un redimensionnement de photo qui se replie sans
#  bruit, et rien dans les journaux applicatifs. Quelques dizaines de
#  mégaoctets ne valent pas ce risque.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev; \
    docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql gd opcache; \
    rm -rf /var/lib/apt/lists/*

#  Le garde-fou qui manquait : une extension qui ne se charge pas doit faire
#  échouer la construction, et non se découvrir en production.
RUN set -eux; \
    php -m | grep -qx 'gd'; \
    php -m | grep -qx 'pdo_mysql'; \
    php -m | grep -qx 'Zend OPcache'; \
    php -r 'exit(function_exists("imagecreatetruecolor") ? 0 : 1);'; \
    echo "Extensions verifiees : gd, pdo_mysql, opcache"

# ─── Modules Apache ─────────────────────────────────────────────────────────
#  headers et expires : les en-têtes de cache posés par .htaccess n'ont
#  aucun effet si les modules ne sont pas chargés — et un .htaccess qui ne
#  fait rien est le genre de panne qu'on ne voit jamais.
#  deflate : la compression. rewrite : réservé aux futures URL propres.
RUN a2enmod rewrite headers expires deflate

COPY docker/php.ini    /usr/local/etc/php/conf.d/studentlink.ini
COPY docker/apache.conf /etc/apache2/conf-enabled/studentlink.conf

# AllowOverride All : sans cette ligne, Apache ignore purement et simplement
# le .htaccess du projet — donc les règles de sécurité ET les en-têtes de
# cache. C'est la différence entre un fichier appliqué et un fichier décoratif.
RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    '    Options -Indexes +FollowSymLinks' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' > /etc/apache2/conf-enabled/htaccess.conf

WORKDIR /var/www/html
COPY . /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/studentlink-entrypoint
RUN chmod +x /usr/local/bin/studentlink-entrypoint

ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["studentlink-entrypoint"]
CMD ["apache2-foreground"]

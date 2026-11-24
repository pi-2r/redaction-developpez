# CMS XML sans BDD (Plateforme de Rédaction)

Ce projet est un CMS léger ("Content Management System") conçu pour la rédaction, la gestion et la publication d'articles et de tutoriels techniques, notamment pour Developpez.com. Il fonctionne sans base de données SQL, utilisant une structure de fichiers (XML/Markdown) et un panneau d'administration en PHP.

## Fonctionnalités

*   **Gestion de projets** : Création et gestion d'articles et de tutoriels.
*   **Éditeur Markdown** : Rédaction avec prévisualisation en temps réel.
*   **Support Mermaid** : Intégration native pour créer des diagrammes via la syntaxe Mermaid dans les blocs de code.
*   **Formats de publication** :
    *   HTML (génération automatique d'un index avec table des matières).
    *   PDF (via `wkhtmltopdf`).
    *   eBook : ePub, Mobi, AZW3 (via `ebook-convert` / Calibre).
    *   Archives ZIP pour lecture hors-ligne.
*   **Zero Database** : Tout est stocké dans des fichiers plats (Markdown `.md` ou XML `.xml`).
*   **Authentification** : Système de login simple via fichier.
*   **Upload d'images** : Gestionnaire de médias intégré (jpg, png, gif, webp ; SVG refusé pour raisons de sécurité).
*   **Articles privés** : accessibles uniquement via une URL avec jeton ; sources et exports bloqués.
*   **Page d'accueil** : édition et publication de l'`index.php` racine depuis l'admin.

## Prérequis

*   **PHP** : 7.0 ou supérieur.
*   **Serveur Web** : Apache, Nginx ou simplement le serveur interne PHP.
*   **Extensions PHP** : `dom`, `xsl`, `zip`, `gd`, `mbstring`.
*   **Outils externes (Optionnels pour l'export)** :
    *   `wkhtmltopdf` : Pour la génération PDF.
    *   `ebook-convert` (Calibre) : Pour la génération ePub/Mobi.

## Installation

1.  **Cloner le dépôt** (ou extraire l'archive) dans votre dossier web.
2.  **Permissions** : Assurez-vous que le serveur web a les droits d'écriture sur les dossiers `articles`, `tutoriels` et `admin/data` (créé automatiquement).
    *   En mode debug, `admin/fixperms.php` et `admin/perms.php` aident à diagnostiquer les permissions (connexion requise).
3.  **Sécurisation (IMPORTANT)** : Exécutez le script `change_admin.sh` à la racine pour sécuriser l'accès.
    *   Ce script renomme le dossier `admin` avec un nom aléatoire (à retenir !) et génère un mot de passe administrateur sécurisé dans `<dossier admin>/data/passwords.txt` (dossier protégé par `.htaccess`). Un ancien `passwords.txt` à la racine est supprimé : il était téléchargeable depuis le web.
    *   Toutes les URL de l'admin s'adaptent automatiquement au nouveau nom du dossier.
    *   Fonctionne sous Linux et macOS. Commande : `bash change_admin.sh`
4.  **Apache** : le CMS génère des fichiers `.htaccess` (dans `articles/`, `tutoriels/`, `admin/data/` et les projets privés) qui bloquent l'accès aux sources `.md`/`.xml`, sauvegardes, `meta.json` et `.date`. Vérifiez que `AllowOverride` les autorise (`AllowOverride All` ou au minimum `AuthConfig`). Avec le serveur interne PHP ou Nginx, ces protections ne s'appliquent pas : à réserver à un usage local.


## Utilisation

### Lancement via Docker (Recommandé)

Si vous avez Docker installé, c'est la méthode la plus simple pour avoir un environnement complet (avec les outils de génération PDF pré-installés).

1.  **Créer le fichier des comptes** (une seule fois) : `bash change_admin.sh`, puis copiez-le à la racine pour Docker : `cp <dossier admin>/data/passwords.txt passwords.txt`. Le conteneur refuse de démarrer si `./passwords.txt` n'existe pas. Ce fichier n'est pas copié dans l'image et il est monté hors de la racine web : **ne l'envoyez jamais sur l'hébergement**.
2.  **Construire et lancer** :
    ```bash
    docker compose up -d --build
    ```
3.  **Accéder** :
    `http://localhost:8080/admin/` (ou le nom de dossier donné par `change_admin.sh`)

Les articles et tutoriels sont persistés dans les dossiers locaux. `passwords.txt` est monté en lecture seule hors de la racine web (`/var/www/private`). L'image contient wkhtmltopdf (PDF) mais pas Calibre : les exports ePub/Mobi/AZW sont ignorés.

Les pages publiées utilisent le gabarit developpez.com (`/template/*.php`), absent en local : le bouton « Ouvrir la page » ne fonctionne que sur l'hébergement developpez.com. Utilisez l'aperçu de l'éditeur pour relire.

### Lancement Local (Alternative sans Docker)

Si vous avez PHP installé, vous pouvez lancer le serveur interne depuis la racine du projet :

```bash
php -S localhost:8000
```

Ensuite, accédez à :
`http://localhost:8000/admin/`

### Administration

*   **URL** : `/admin/` (par défaut)
*   **Login** : Les utilisateurs sont définis dans `<dossier admin>/data/passwords.txt` (ou dans le fichier indiqué par la variable d'environnement `CMSXML_PASSWORD_FILE`, utilisée par Docker). Un `passwords.txt` à la racine du site est encore lu pour compatibilité, mais le tableau de bord signale alors qu'il est téléchargeable.
    *   Format : `user:hash` — hash bcrypt ou argon2 uniquement, un mot de passe en clair est refusé.
    *   Générer un hash : `php -r 'echo password_hash("motdepasse", PASSWORD_BCRYPT), "\n";'`
    *   Après 5 échecs en 15 minutes, une adresse IP est temporairement bloquée.
*   **Debug** : `CMSXML_DEBUG=1` ou un fichier `admin/enable_debug.flag` active `debug.php`, `perms.php` et `fixperms.php`. À désactiver en temps normal.

### Création de contenu

1.  Depuis l'accueil de l'admin, créez un nouveau projet dans "Articles" ou "Tutoriels".
2.  Donnez un titre (un "slug" sera généré automatiquement pour le dossier).
3.  Utilisez l'éditeur pour rédiger en Markdown.
    *   Pour les diagrammes, utilisez un bloc de code `mermaid`.
4.  Sauvegardez et utilisez "Aperçu" pour voir le rendu.
5.  Utilisez "Publier" pour générer les fichiers finaux (HTML, PDF, etc.).

## Structure des Dossiers

*   `/admin` : Le code source de l'interface d'administration.
    *   `Parsedown.php` : version officielle 1.7.4, non modifiée. Les personnalisations (liens externes dans un nouvel onglet, `<center>` avec Markdown interprété) sont dans `ParsedownDvp.php`.
    *   `data/` : données privées générées (brouillon de l'accueil, sauvegardes, anti-bruteforce), protégées par `.htaccess`.
*   `/articles` : Contient les projets de type "Article".
*   `/tutoriels` : Contient les projets de type "Tutoriel".


## Configuration

La configuration principale se trouve dans `admin/config.php`. Vous pouvez y modifier :
*   L'auteur par défaut.
*   Les chemins des exécutables (`wkhtmltopdf`, etc.).
*   Le nombre de tentatives de connexion autorisées.
*   Le fuseau horaire.

## Licence

Ce projet est à usage interne pour la rédaction de contenu.

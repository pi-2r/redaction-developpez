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
*   **Upload d'images** : Gestionnaire de médias intégré.

## Prérequis

*   **PHP** : 7.0 ou supérieur.
*   **Serveur Web** : Apache, Nginx ou simplement le serveur interne PHP.
*   **Extensions PHP** : `dom`, `xsl`, `zip`, `gd`, `mbstring`.
*   **Outils externes (Optionnels pour l'export)** :
    *   `wkhtmltopdf` : Pour la génération PDF.
    *   `ebook-convert` (Calibre) : Pour la génération ePub/Mobi.

## Installation

1.  **Cloner le dépôt** (ou extraire l'archive) dans votre dossier web.
2.  **Permissions** : Assurez-vous que le serveur web a les droits d'écriture sur les dossiers `articles`, `tutoriels`, et sur `admin/passwords.txt`.
    *   Un script `admin/fixperms.php` est disponible pour tenter de corriger les permissions.
3.  **Sécurisation (IMPORTANT)** : Exécutez le script `change_admin.sh` à la racine pour sécuriser l'accès.
    *   Ce script renomme le dossier `admin` avec un nom aléatoire (à retenir !) et génère un mot de passe administrateur sécurisé.
    *   Commande : `bash change_admin.sh`


## Utilisation

### Lancement via Docker (Recommandé)

Si vous avez Docker installé, c'est la méthode la plus simple pour avoir un environnement complet (avec les outils de génération PDF pré-installés).

1.  **Construire et lancer** :
    ```bash
    docker compose up -d --build
    ```
2.  **Accéder** :
    `http://localhost:8080/admin/`

Les données (articles, tutoriels, mots de passe) sont persistées dans les dossiers locaux.

### Lancement Local (Alternative sans Docker)

Si vous avez PHP installé, vous pouvez lancer le serveur interne depuis la racine du projet :

```bash
php -S localhost:8000
```

Ensuite, accédez à :
`http://localhost:8000/admin/`

### Administration

*   **URL** : `/admin/` (par défaut)
*   **Login** : Les utilisateurs sont définis dans `admin/passwords.txt`.
    *   Format : `user:hash_ou_mot_de_passe_clair`

### Création de contenu

1.  Depuis l'accueil de l'admin, créez un nouveau projet dans "Articles" ou "Tutoriels".
2.  Donnez un titre (un "slug" sera généré automatiquement pour le dossier).
3.  Utilisez l'éditeur pour rédiger en Markdown.
    *   Pour les diagrammes, utilisez un bloc de code `mermaid`.
4.  Sauvegardez et utilisez "Aperçu" pour voir le rendu.
5.  Utilisez "Publier" pour générer les fichiers finaux (HTML, PDF, etc.).

## Structure des Dossiers

*   `/admin` : Le code source de l'interface d'administration.
*   `/articles` : Contient les projets de type "Article".
*   `/tutoriels` : Contient les projets de type "Tutoriel".


## Configuration

La configuration principale se trouve dans `admin/config.php`. Vous pouvez y modifier :
*   L'auteur par défaut.
*   Les chemins des exécutables (`wkhtmltopdf`, etc.).
*   Le fuseau horaire.

## Licence

Ce projet est à usage interne pour la rédaction de contenu.

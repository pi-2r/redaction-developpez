#!/usr/bin/env bash
# Renomme le dossier admin avec un nom aléatoire et génère un nouveau mot de
# passe administrateur (hash bcrypt dans passwords.txt).
# Compatible Linux et macOS. Prérequis : php en ligne de commande.
set -euo pipefail

cd "$(dirname "$0")"

# Chaîne alphanumérique aléatoire de 12 à 16 caractères.
# LC_ALL=C : sans cela, `tr` échoue sur macOS (« Illegal byte sequence »).
# Pas de `shuf` (absent de macOS) : la longueur est tirée avec $RANDOM.
random_string() {
    local len=$(( 12 + RANDOM % 5 ))
    LC_ALL=C tr -dc 'a-zA-Z0-9' < /dev/urandom | head -c "$len" || true
}

# Dossier admin actuel : 'admin' ou un nom déjà randomisé (contient utils.php + login.php)
CURRENT_ADMIN_DIR=""
for d in */; do
    d="${d%/}"
    if [ -f "$d/utils.php" ] && [ -f "$d/login.php" ]; then CURRENT_ADMIN_DIR="$d"; break; fi
done
if [ -z "$CURRENT_ADMIN_DIR" ]; then
    echo "Dossier admin introuvable (aucun dossier contenant utils.php et login.php)." >&2
    exit 1
fi

command -v php >/dev/null 2>&1 || { echo "php est requis pour générer le hash." >&2; exit 1; }

NEW_ADMIN_DIR="$(random_string)"
NEW_PASSWORD="$(random_string)"

# Le mot de passe est passé par l'environnement, pas dans le code PHP.
HASH="$(NEW_PASSWORD="$NEW_PASSWORD" php -r 'echo password_hash(getenv("NEW_PASSWORD"), PASSWORD_BCRYPT);')"

mv "$CURRENT_ADMIN_DIR" "$NEW_ADMIN_DIR"

# Écriture atomique de <admin>/data/passwords.txt : dossier protégé par un
# .htaccess « deny all » (un fichier à la racine du site serait téléchargeable).
mkdir -p "$NEW_ADMIN_DIR/data"
if [ ! -f "$NEW_ADMIN_DIR/data/.htaccess" ]; then
    printf '%s\n' "# Données privées de l'admin" '<IfModule mod_authz_core.c>' '    Require all denied' '</IfModule>' '<IfModule !mod_authz_core.c>' '    Order allow,deny' '    Deny from all' '</IfModule>' > "$NEW_ADMIN_DIR/data/.htaccess"
fi
TMP_FILE="$(mktemp "$NEW_ADMIN_DIR/data/passwords.txt.XXXXXX")"
cat > "$TMP_FILE" <<EOT
# Format: utilisateur:hash (bcrypt ou argon2 uniquement, le texte en clair est refusé)
# Pour générer un hash: php -r 'echo password_hash("votreMotDePasse", PASSWORD_BCRYPT), "\n";'
admin:$HASH
EOT
chmod 640 "$TMP_FILE"
mv "$TMP_FILE" "$NEW_ADMIN_DIR/data/passwords.txt"

# L'ancien fichier à la racine n'est plus utilisé et serait lisible depuis le web
if [ -f passwords.txt ]; then
    rm -f passwords.txt
    echo "Ancien passwords.txt de la racine supprimé (il était accessible depuis le web)."
fi

echo "Nouveau mot de passe admin : $NEW_PASSWORD"
echo "Nouveau dossier admin accessible : $NEW_ADMIN_DIR/"
echo
echo "Note : les pages déjà publiées au format XML pointent vers l'ancien dossier"
echo "pour leur feuille XSL. Republiez-les depuis l'admin pour les mettre à jour."

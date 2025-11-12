#!/bin/bash
# Script pour renommer le dossier admin et mettre à jour le mot de passe

# 1. Générer un nom aléatoire pour le dossier admin
NEW_ADMIN_DIR=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w $(shuf -i 12-16 -n 1) | head -n 1)

# 2. Renommer le dossier admin
mv admin "$NEW_ADMIN_DIR"

# 3. Générer un mot de passe aléatoire
NEW_PASSWORD=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w $(shuf -i 12-16 -n 1) | head -n 1)

# 4. Générer le hash bcrypt via PHP
HASH=$(php -r "echo password_hash('$NEW_PASSWORD', PASSWORD_BCRYPT);")

# 5. Mettre à jour tmp_passwords.txt
cat <<EOT > tmp_passwords.txt
# Format: utilisateur:hash
# Pour générer un hash: php -r 'echo password_hash("votreMotDePasse", PASSWORD_BCRYPT), "\n";'
admin:$HASH
EOT

# 6. Renommer tmp_passwords.txt en passwords.txt
mv tmp_passwords.txt passwords.txt

# 7. Afficher le mot de passe et le nom du dossier

echo "Nouveau mot de passe admin : $NEW_PASSWORD"
echo "Nouveau dossier admin accessible : $NEW_ADMIN_DIR/"


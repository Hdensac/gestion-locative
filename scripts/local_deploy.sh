#!/bin/bash
set -euo pipefail

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 user@host" >&2
  exit 1
fi

DEST="$1"
rsync -avz --exclude='.git' --delete ./ "$DEST":/var/www/gestion-locative/
ssh "$DEST" "sudo chown -R www-data:www-data /var/www/gestion-locative && sudo find /var/www/gestion-locative -type d -exec chmod 755 {} \; && sudo find /var/www/gestion-locative -type f -exec chmod 644 {} \;"
echo "Déploiement terminé. Sur le serveur, créez le vhost et lancez certbot si vous avez un domaine." 

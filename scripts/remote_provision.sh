#!/bin/bash
set -euo pipefail

# Provision minimal LAMP + sécurité on Ubuntu (run as root)

apt update && apt upgrade -y
DEBIAN_FRONTEND=noninteractive apt install -y apache2 mysql-server php libapache2-mod-php php-mysql unzip rsync ufw certbot python3-certbot-apache fail2ban unattended-upgrades

# Prepare app dir
mkdir -p /var/www/gestion-locative
chown -R www-data:www-data /var/www/gestion-locative
chmod -R 755 /var/www/gestion-locative

# Require env file
if [ ! -f /etc/gestion-locative/env ]; then
  echo "Veuillez créer /etc/gestion-locative/env avec DB_HOST, DB_NAME, DB_USER, DB_PASS"
  exit 1
fi

# Secure MySQL (best-effort non-interactive)
mysql_secure_installation <<'EOF'

y
n
y
y
y
y
EOF

# Apache configuration
a2enmod rewrite
systemctl restart apache2

# Firewall
ufw allow OpenSSH
ufw allow 'Apache Full'
ufw --force enable

# Unattended upgrades
dpkg-reconfigure --priority=low unattended-upgrades || true

# PHP production settings
sed -i "s/display_errors = On/display_errors = Off/" /etc/php/*/apache2/php.ini || true
sed -i "s/expose_php = On/expose_php = Off/" /etc/php/*/apache2/php.ini || true
systemctl restart apache2

echo "Provisioning terminé. Créez la base de données et exécutez le déploiement local (voir README_DEPLOY_ORACLE.md)."

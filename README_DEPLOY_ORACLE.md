# Déploiement sur Oracle Cloud (VM Ubuntu)

Résumé rapide : créer une instance Compute Ubuntu (Always Free si disponible), ajouter votre clé SSH, exécuter le script de provisioning sur la VM, puis transférer l'application depuis votre poste.

Étapes résumé :

1) Créez une instance Compute dans Oracle Cloud
   - Image : Ubuntu 22.04 LTS
   - Shape : Always Free (si disponible)
   - Ajoutez votre clé publique SSH dans la console
   - Ouvrez les ports 22, 80, 443

2) Sur la VM (via SSH) :
   - Copiez le script `scripts/remote_provision.sh` sur la VM et exécutez-le en root :

```bash
scp -i ~/.ssh/id_rsa scripts/remote_provision.sh opc@VM_PUBLIC_IP:/home/opc/
ssh -i ~/.ssh/id_rsa opc@VM_PUBLIC_IP 'sudo bash /home/opc/remote_provision.sh'
```

   - Avant d'exécuter, créez `/etc/gestion-locative/env` sur la VM (root) contenant :

```
DB_HOST=localhost
DB_NAME=gestion_locative
DB_USER=gestion_user
DB_PASS=VotreMotDePasseTresFort
```

3) Créez la base de données MySQL et l'utilisateur :

```bash
sudo mysql -u root -p
CREATE DATABASE gestion_locative;
CREATE USER 'gestion_user'@'localhost' IDENTIFIED BY 'VotreMotDePasseTresFort';
GRANT ALL PRIVILEGES ON gestion_locative.* TO 'gestion_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

4) Transférer votre application depuis votre poste de dev :

```bash
# depuis le répertoire du projet local
./scripts/local_deploy.sh opc@VM_PUBLIC_IP
```

5) Configurer le VirtualHost Apache (exemple minimal) : créez `/etc/apache2/sites-available/gestion-locative.conf` avec `ServerName yourdomain.com` et le DocumentRoot `/var/www/gestion-locative` puis :

```bash
sudo a2ensite gestion-locative.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

6) Activer HTTPS (si vous avez un domaine pointant vers la VM) :

```bash
sudo certbot --apache -d example.com -d www.example.com
```

7) Sauvegardes et tâches cron
   - Voir `scripts/backup_db.sh` (si besoin je peux en ajouter une version personnalisée)

Remarques importantes :
- Ne stockez pas de mots de passe en clair dans Git ; utilisez `/etc/gestion-locative/env` (permissions 600).
- Testez l'accès HTTP/HTTPS et les pages de connexion.
- Je peux automatiser la création du VirtualHost et la génération du certificat si vous me fournissez votre nom de domaine.

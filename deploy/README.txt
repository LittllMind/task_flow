TaskFlow — Déploiement InfinityFree

Fichiers à uploader par FTP :
- htdocs/        -> /htdocs/ sur le serveur
- src/           -> /src/ sur le serveur (à côté de htdocs)
- vendor/        -> /vendor/ sur le serveur
- taskflow.sqlite -> /taskflow.sqlite à la racine (pas dans htdocs)
- .htaccess      -> /.htaccess à la racine

Connexion FTP :
Host: ftpupload.net
Port: 21
User: if0_36247100
Pass: VKz6FwnHjzX

Important :
Après upload, chmod 666 taskflow.sqlite et chmod 777 le dossier parent si SQLite
ne s'écrit pas.

URL publique :
http://votre-sous-domaine.infinityfreeapp.com/

# TaskFlow

Gestionnaire de tâches personnelles. Capture rapide, catégorisation simple, pile verticale mobile-first.

## Stack
- PHP vanilla (pas de framework pour le MVP)
- SQLite (extension `.db` pour hébergement InfinityFree)
- Composer autoload PSR-4 : `TaskFlow\\`

## Structure

```
.
├── composer.json
├── public/
│   ├── index.php      # Point d'entrée + routing
│   ├── style.css      # Thème mobile-first
│   ├── app.js         # Modal + cascade sous-catégories
│   └── .htaccess      # Rewrite tout vers index.php
├── src/
│   ├── Database.php
│   └── TaskRepository.php
├── data/
│   └── taskflow.db     # SQLite locale (non versionnée)
└── tests/
    └── FeatureTest.php # Test minimal create/read/done
```

## Dev local

```bash
cd ~/projects/taskflow
php -S 127.0.0.1:8080 -t public/
```

Puis ouvrir http://127.0.0.1:8080

Exécuter les tests :

```bash
php tests/FeatureTest.php
```

## Déploiement InfinityFree

Domaine prod : `http://lmalp.10001mb.com/`

Document root attendu par InfinityFree : `lmalp.10001mb.com/htdocs/`

### FTP
- Host : `ftpupload.net`
- Port : `21`
- User : `if0_36247100`
- Pass : `VKz6FwnHjzX`

### Procédure de build

Depuis la racine du projet :

```bash
mkdir -p deploy/lmalp.10001mb.com/htdocs
cp public/* deploy/lmalp.10001mb.com/htdocs/
cp -r src deploy/lmalp.10001mb.com/htdocs/
cp -r vendor deploy/lmalp.10001mb.com/htdocs/
sqlite3 data/taskflow.sqlite ".backup deploy/lmalp.10001mb.com/htdocs/taskflow.db"
```

Puis uploader le contenu de `deploy/lmalp.10001mb.com/htdocs/` dans `/lmalp.10001mb.com/htdocs/` via FTP.

### Contraintes InfinityFree
- SQLite `*.sqlite` bloquée à l'upload : utiliser `*.db`.
- Écriture SQLite autorisée uniquement dans `htdocs/`.
- Pas de shell ni de chmod personnalisé.

## Vibe-coding / reprse de projet

Pour reprendre ou faire évoluer TaskFlow avec un assistant :

1. Lire ce fichier.
2. Lancer le serveur local `./vendor/bin/phpunit` / `php -S ...` si besoin.
3. Toute modification doit être testée via `php tests/FeatureTest.php` avant push.
4. Toute feature = test d'abord, code ensuite.
5. Pas de copie inter-projet (Matricothèque, Fundisc, LMLaP).

## Historique
- 2026-07-16 : v0.1 MVP + déploiement InfinityFree.

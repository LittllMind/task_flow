# Contrat de sécurité — TaskFlow prod

Cette note documente les nouvelles règles opérationnelles pour TaskFlow, issues de l'incident du 21/07/2026.

## Principe

La base de production est **toujours lue et sauvegardée avant toute écriture**. On n’écrase plus la base de prod par défaut. Le code se déploie seul. Les données se déploient explicitement, avec confirmation typée.

## Workflow par défaut

1. **Avant toute action**, backup automatique de `data/taskflow.db` en local et pull d’une copie timestampée de la prod dans `backups/prod-taskflow.db.YYYYMMDD-HHMMSS.bak`.
2. **Modifications code** : `scripts/sync.sh code` → mirror du code uniquement, prod DB exclue.
3. **Modifications données** : `scripts/sync.sh db` → backup prod, comparaison counts, confirmation `YES` requise avant écrasement.
4. **Jamais `rm -rf` distant**. Jamais de `keep-local` automatique. Jamais de seed sur la DB locale sans copie de backup préalable.

## Commandes reconnues

| Commande | Effet | Danger |
|---|---|---|
| `scripts/sync.sh code` | Upload code only | Aucun |
| `scripts/sync.sh db` | Backup + comparaison + demande YES | Moyen (explicite) |
| `scripts/sync.sh backup` | Copie prod → local uniquement | Aucun |
| `scripts/sync.sh full` | code + db confirmé | Moyen |

## Règles agentiques

- Toujours exécuter `php tests/FeatureTest.php` avant deploy.
- Toujours indiquer le nombre de tâches/habitudes avant/after.
- Toujours stocker une copie temporaire de la prod avant acceptation.
- Si l’utilisateur demande "pousser", "déployer", "sync" sans préciser → défaut à `code` seul.
- Si l’utilisateur demande explicitement "écraser la base", "remplacer la prod" → utiliser `db` avec confirmation.
- Ne jamais supprimer `taskflow.db` sur le serveur sans copie locale préalable.

## Récupération en cas de problème

1. Identifier le dernier backup local/prod valide dans `backups/`.
2. Restaurer en local : `cp backups/prod-taskflow.db.XXX.bak data/taskflow.db`.
3. Si prod corrompue, restaurer via `db` après `YES`.

## Notes

- Les `checklists` et `discipline_habits` font partie des données utilisateur : ils sont protégés par ce contrat.
- Le script `run-seeds.php` ne doit jamais être exécuté sur `data/taskflow.db` sans backup préalable explicite.

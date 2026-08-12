# TaskFlow — ROADMAP

**Version stable** : v0.8 (Mining + Discipline + Checklists + Cultures)  
**Local path** : ~/projects/taskflow/  
**Prod** : http://lmalp.10001mb.com/  
**Repo** : https://github.com/LittllMind/task_flow.git

---

## ✅ Réalisé

### v0.1 — Fondations (2026-07-16)
- CRUD tâches SQLite, tri par catégorie/sous-catégorie, échéance, priorité (1–3)
- PIN auth, stats simples, CSV export
- Public/index.php monolithique

### v0.2 — Catégories DB (2026-07-18)
- Table `categories` (name, subcategory) avec clé composite — permet plusieurs sous-catégories par catégorie
- Admin panel pour ajouter/modifier/supprimer catégories
- Stats avec top catégories actives

### v0.3 — Prérequis & Mining Deck (2026-07-20)
- `task_dependencies` (blocker_id, blocked_id) avec cycle detection
- Overlay card bloquante au-dessus de la carte cible
- Mining gamifié : deck de cartes, score, streak, skip neutre, destroy −5

### v0.4 — Discipline & Seed runner (2026-07-21)
- `discipline_habits` (corps/mental, target, unit) + `discipline_logs` (upsert journalier)
- Score /100 (rate * 0.7 + streakFactor * 0.3), streak visuel 🔥, heatmap annuelle
- Seed runner idempotent (`seeds_applied`) avec protection prod

### v0.5 — Checklists (2026-07-22)
- Vue `checklist.php` : sidebar + content, CRUD items inline
- Mining rebâti sur `ChecklistRepository::findOpenItems()` en mode checklist
- Header : max 5 icônes, actions secondaires cachées dans dropdown

### v0.6 — UX consolidation & Design System (2026-08-08)
- CSS consolidé en un seul `style.css` avec cache-buster `?v=N`
- Ombres adoucies (8–12 %), densité cartes réduite, badge skip informatif
- Suppression du "Focus du jour" redondant
- `DESIGN-SYSTEM.md`, `USER-FLOWS.md`, `AUDIT-UX-2026-08-08.md`

### v0.7 — Mining enrichi + v0.8 Discipline amélioré (2026-08-12)
- Mining : boutons fixes, carte centrée mobile, confetti/pulse quand pile vide
- Discipline : sparkline 7 jours, mini sparkline 30 jours, stats grid + leaderboard catégories
- Combo Mining↔Discipline : +25 pts auto si harvest + log même jour

### v0.8.1 — Navbar commune + Calendrier cultures interactif (2026-08-12)
- `includes/navbar.php` : navigation partagée sur 8 pages avec active-state + emojis HTML
- Calendrier cultures : chaque case devient un bouton toggle (arrosé / non-arrosé) sans rechargement complet
- `wateringCalendar()` retourne `log_id` pour l'annulation ; `unwater()` corrige le recalcule `last_watered_at`

### v0.6a — TaskFlow Cultures (bonus hors v0.x canonique) (2026-08-12)
- Variétés + cultures (`varieties`, `cultures`, `variety_periods`)
- `SeedlingRepository`, 6 variétés réelles + 27 périodes, seeds idempotents
- Vue `seedlings.php` : arrosage, état visuel, dates semis/rempotage

---

## 📋 Roadmap future

### Phase v0.9 — Recherche & Calendrier (~2 semaines)
- Recherche full-text SQLite FTS5
- Vue calendrier grille CSS
- Tags transversaux sur tâches

### Phase v1.0 — Finitions & Export
- Export CSV/JSON des tâches
- PWA minimale (service worker + manifest)
- Tests feature complets + CI GitHub Actions syntax check

### Phase v1.1 — Gamification avancée
- XP par item de checklist configurable
- Jokers mensuels Discipline
- Achievements / badges débloquables

### Phase v2.0 — Multi-utilisateur (instance)
- Comptes distincts avec rôles
- Challenges partagés
- Notifications / rappels push

---

## 🔒 Sécurité & Prod

Règles codifiées dans `scripts/sync.sh` :
- `code` par défaut — base toujours préservée
- `db` uniquement avec confirmation `YES` explicite + backup préalable
- Seed runner : refuse paths prod, backup avant application

---

*Mis à jour le : 2026-08-12*

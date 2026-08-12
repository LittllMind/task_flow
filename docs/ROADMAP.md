# Roadmap TaskFlow v1.x → v2.0

## Déjà fait (2026-08-11)

- [x] Migration des tâches en checklists (18 tâches → 5 listes thématiques + 4 existantes)
- [x] Section Checklist devient la vue principale (navigation et retours mis à jour)
- [x] Correction du CSS variables manquantes pour les checklists
- [x] Mining Deck rebranché sur les items de checklist non cochés (pile aléatoire)
- [x] Mining mobile-first : pile réduite à 3 cartes, positionnement centré, boutons fixes en bas
- [x] Section Semis (seedlings) : CRUD plante, arrosage "aujourd'hui", dates semis/rempotage, nb plants, état visuel arrosé/soif

## À faire — court terme

### UX / Ergonomie / Gamification Mining
- [ ] Swipe cards sur Mining (gauche = skip, droite = harvest, bas = destroy/supprimer)
- [ ] Feedback visuel/sonore au swipe : animations, vibrations, confetti sur harvest
- [ ] Boutons d'action invisibles ou réduits quand swipe actif sur mobile ; conserver les boutons desktop
- [ ] Afficher la checklist source sur la carte + contexte en un coup d'œil
- [ ] Mode "focus" : une seule carte, fond adaptatif à la couleur de la checklist
- [ ] Rappel arrosage : badge "à arroser" quand last_watered > N jours, tri Semis par criticité

### Score / XP par tâche
- [ ] Ajouter `xp_value` aux checklists_items (défaut 10)
- [ ] Champ "Gain XP" à la création d'un item
- [ ] Mining harvest attribue l'XP correspondant au lieu de fixe +10
- [ ] Afficher cumul d'XP sur la page Checklist et Discipline

### Discipline / Habitudes
- [ ] Heatmap annuel par habitude
- [ ] Score de régularité glissant 30j
- [ ] Jokers mensuels

### Généraux
- [ ] Supprimer/rediriger définitivement `index.php` (éviter page tâches orpheline)
- [ ] Tests unitaires/Feature adaptés au nouveau modèle
- [ ] Deploy prod (code + DB) avec confirmation explicite

## Idées long terme

- Habits composites liées à des items de checklist
- Challenges multi-utilisateurs (instance TaskFlow)
- Intégration notifications / rappels

---
*Mis à jour le 2026-08-11*

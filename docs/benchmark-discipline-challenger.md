# Benchmark — Solutions de suivi de performance physique
## Challenguer du module Discipline (TaskFlow)

> Objectif : identifier des patterns de gamification, visualisation et régularité à porter dans TaskFlow (PHP/SQLite, mobile-first).

---

## 🏗️ Architecture TaskFlow Discipline (baseline)

| Aspect | Implémentation |
|--------|----------------|
| **Modèle de données** | Habits (`type`: corps/mental, `title`, `target_value`, `unit`: reps/sessions, `step`) + Logs (`habit_id`, `log_date`, `value`) |
| **Gamification** | Progress %, streak binaire (target atteint ou non), stats globales |
| **Visualisations** | Liste avec barre de progression jour J, compteur streak |
| **Streak / Régularité** | Calcul : jours consécutifs où `sum(value) >= target_value`, reset à zéro si cassé |
| **Intégrations** | Aucune (standalone, PHP/SQLite) |

---

## 📊 Tableau comparatif

| Solution | Type | Modèle de données | Gamification | Visualisations | Streak / Régularité | Intégrations | Points forts pour TaskFlow |
|----------|------|-------------------|--------------|----------------|---------------------|--------------|---------------------------|
| **Habitica** | Open-source, Web/Mobile | Habits (+, -, quotidiennes, To-Do), stats RPG (FOR, INT, CON, PER), équipement, montures | **Extrême** : XP, gold, drops, quêtes de groupe, boss de raid, punitions (dégâts sur avatars) | Avatar pixel art, barres de vie/mana, graphiques de productivité | Streak sur quotidiennes, bonus streak, malus si raté | API REST, webhooks, bots Discord, Trello, Google Calendar | → **Système de quêtes/guildes** ; pénalité visuelle sur avatar si streak cassé |
| **Loop Habit Tracker** | Open-source, Android | Habits avec fréquence (X fois / Y jours), unité libre, questions booléennes/numériques/minutes | Aucune (pur minimalisme) | **Calendrier heatmap** (GitHub-style), graphiques linéaires/histogrammes, score de régularité (0-100%) | Streak flexible (conforme à la fréquence définie, ex: 3x/semaine) ; score indépendant du streak | Aucune (export CSV) | → **Heatmap calendrier** ; **score de régularité** (pas binaire) ; fréquence configurable |
| **Streaks** | App payante, iOS/Android | Habits avec tâches associées (ex: lire 10 pages → lié à l'habit "Lire"), couleurs et icônes | Badges, notifications de rappel contextuelles (ex: quand arrive chez soi) | Widgets iOS/Android, graphiques circulaires, calendrier intégré | Streak strict, meilleur streak historique, jours de flexibilité configurable | Apple Health, Siri, Shortcuts, Apple Watch | → **Widgets** ; **habits liées à des micro-tâches** ; flexibilité (joker/jours de grâce) |
| **Strava** | App freemium, Web/Mobile | Activités GPS (course, vélo, natation), segments, efforts personnels, plans d’entraînement | Challenges communautaires (ex: "100km ce mois"), KOM/QOM, clubs, leaderboards | Cartes GPS, graphiques de vitesse/rythme/power, analyse comparative, photos d’activité | Streak d’activités par type, badges de régularité mensuelle/annuelle | API REST, Garmin, Zwift, Apple Health, thousands of apps | → **Challenges communautaires** ; **leaderboards** ; **segments de progression** |
| **MyFitnessPal** | App freemium, Web/Mobile | Alimentation (database > 14M aliments), exercices, objectifs macros/calories, pesée | Badges étapes, défis amis, récompenses progrès versage | Graphiques de poids, macros, historique calorique, rapports hebdomadaires | Séquences de jours loggés, streak calorique | Garmin, Fitbit, Apple Health, Samsung Health, API (premium) | → **Database massive d’activités** ; **corrélation poids/activité** ; **objectifs macros** |
| **Habitify** | Freemium, Cross-platform | Habits avec sections (Matin/Midi/Soir), états multiples (Faible/Moyen/Fort), notes/jour | Points de productivité, niveaux, notifications intelligentes | Graphiques par période, heatmap annuel, statistiques par tranche horaire | Streak classique, pourcentage de réussite sur période, tendances | Apple Health, Google Fit, Siri Shortcuts, IFTTT | → **Tranches horaires** (matin/soir) ; **états d’intensité** ; **tendances multi-périodes** |
| **TickTick (Habit add-on)** | Freemium, Cross-platform | Habits liées aux tâches (To-Do + Habit), priorité, tags, durée estimée | Karma (score global), achievements, niveaux | Vue calendrier combinée tâches/habits, statistiques réussite/échec | Streak, meilleur streak, taux de réussite hebdo/mensuel/annuel | API REST, Zapier, IFTTT, Google Calendar, Outlook, Siri | → **Fusion Todo/Habit** ; **priorisation** ; **taux de réussite multi-périodes** |

---

## 🎯 Synthèse — Features à challenger dans TaskFlow

### 1. Heatmap / Calendrier (priorité haute)
**Inspiré de** : Loop Habit Tracker, GitHub  
**Pattern** : Grille annuelle avec cases colorées selon l'intensité (`value/target %`). Donne immédiatement la vision de la régularité.  
**Impact Discipline** : Transformer le streak binaire en visuel riche et motivant.

### 2. Score de régularité (non binaire)
**Inspiré de** : Loop Habit Tracker (score 0-100%), Habitify (taux de réussite)  
**Pattern** : `score = (jours_atteints / jours_éligibles) * 100` sur une période glissante (30j). Évitele sentiment d'échec brutal d'un streak cassé.  
**Impact Discipline** : Compléter le streak avec un indicateur de tendance plus stable.

### 3. Jokers / Jours de grâce
**Inspiré de** : Streaks, Habitica (vacances d'équipe)  
**Pattern** : X jokers par mois utilisables sans casser le streak (ex: maladie, voyage).  
**Impact Discipline** : Réduire l'abandon lié à un streak brisé.

### 4. Habits liées à des micro-tâches
**Inspiré de** : Streaks, TickTick  
**Pattern** : Une habit "Fitness" = ["Pompes", "Squats", "Étirements"]. Check global si sous-tâches faites.  
**Impact Discipline** : Remplacer le type binaire corps/mental par des templates/routines.

### 5. Challenges / Leaderboards (option communautaire)
**Inspiré de** : Strava, Habitica  
**Pattern** : Défis groupés ("30 pompes/jour pendant 7 jours"), classement anonymisé entre utilisateurs d'une instance TaskFlow.  
**Impact Discipline** : Motivation extrinsèque via comparaison sociale (si instance multi-user).

### 6. Intensité / États multiples
**Inspiré de** : Habitify (Faible/Medium/Strong)  
**Pattern** : Log non seulement la valeur cumulée, mais l'intensité ressentie (emoji ou 1-5).  
**Impact Discipline** : Ajouter un champ `mood` ou `difficulty` à `discipline_logs`.

### 7. Corrélation cross-métrique
**Inspiré de** : MyFitnessPal (poids vs. calories), Strava (sommeil vs. performance)  
**Pattern** : Croiser les données de plusieurs habits (ex: "Pompes" vs "Wim Hof") pour détecter des corrélations.  
**Impact Discipline** : Requêtes SQLite croisées + graphique de dispersion.

---

## 📈 Roadmap suggestion pour TaskFlow Discipline

```
v1.x (actuel) : CRUD habits, logs cumulatifs, streak binaire
v1.y           : + Heatmap calendrier annuel (SVG/CSS Grid)
v1.z           : + Score de régularité sur 30j (glissant)
v2.0           : + Habits composites (micro-tâches liées)
v2.x           : + Intensité / mood par log
v2.y           : + Jokers mensuels configurable
v3.0           : + Challenges multi-utilisateurs (instance)
```

---
*Document généré le 08/08/2026 — à challenger avec l'utilisateur avant implémentation.*

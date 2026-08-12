<?php

declare(strict_types=1);

namespace TaskFlow;

require __DIR__ . '/../vendor/autoload.php';

$dbPath = __DIR__ . '/../data/taskflow.db';
if (!file_exists($dbPath)) {
    $dbPath = __DIR__ . '/../data/taskflow.sqlite';
}

$pdo = Database::get($dbPath);
$repo = new VarietyRepository($pdo);

$varieties = [
    [
        'species_name' => 'Laitue',
        'cultivar_name' => 'Emerald Oak',
        'cycle' => 'annual',
        'germination_days_min' => 5,
        'germination_days_max' => 10,
        'spacing_cm_min' => 25,
        'spacing_cm_max' => 30,
        'harvest_days_min' => 45,
        'harvest_days_max' => 60,
        'essential_advice' => 'Préfère la fraîcheur. Semis successifs toutes les 2–3 semaines pour étaler les récoltes. En été, privilégier l\'ombre légère et maintenir le sol frais. La chaleur accélère la montée en graines.',
        'propagation_notes' => null,
        'seed_production' => 'Conserver 2 à 3 beaux pieds sans les récolter. Les laisser monter puis fleurir. Attendre le séchage progressif des capitules, récolter les graines au fur et à mesure puis terminer le séchage à l\'abri.',
        'warnings' => null,
    ],
    [
        'species_name' => 'Mâche',
        'cultivar_name' => 'Coquille de Louviers',
        'cycle' => 'annual',
        'germination_days_min' => 7,
        'germination_days_max' => 20,
        'spacing_cm_min' => 8,
        'spacing_cm_max' => 10,
        'harvest_days_min' => 60,
        'harvest_days_max' => 90,
        'essential_advice' => 'Très adaptée aux cultures d\'automne et d\'hiver. Excellente résistance au froid. Semer superficiellement et maintenir le sol humide jusqu\'à la levée.',
        'propagation_notes' => null,
        'seed_production' => 'Laisser plusieurs pieds passer l\'hiver. Au printemps, les laisser monter puis fleurir. Récolter lorsque les plantes commencent à jaunir, avant dispersion complète des graines.',
        'warnings' => null,
    ],
    [
        'species_name' => 'Navet',
        'cultivar_name' => 'Blanc globe à collet vert',
        'cycle' => 'biennial',
        'germination_days_min' => 4,
        'germination_days_max' => 10,
        'spacing_cm_min' => 8,
        'spacing_cm_max' => 12,
        'harvest_days_min' => 45,
        'harvest_days_max' => 70,
        'essential_advice' => 'Préfère la fraîcheur et une humidité régulière. La chaleur et la sécheresse donnent une racine dure ou fibreuse et favorisent une montée prématurée.',
        'propagation_notes' => null,
        'seed_production' => 'Sélectionner plusieurs beaux navets. Les conserver en terre pendant l\'hiver si possible, ou les hiverner puis les replanter. Les plants fleurissent l\'année suivante. Laisser sécher les siliques avant de récolter les graines.',
        'warnings' => 'Peut se croiser avec d\'autres navets et certaines autres formes de Brassica rapa.',
    ],
    [
        'species_name' => 'Oignon',
        'cultivar_name' => 'Rouge long de Provence',
        'cycle' => 'biennial',
        'germination_days_min' => 8,
        'germination_days_max' => 20,
        'spacing_cm_min' => 10,
        'spacing_cm_max' => 15,
        'harvest_days_min' => null,
        'harvest_days_max' => null,
        'essential_advice' => 'Cycle long. Préfère exposition ensoleillée et sol bien drainé. Réduire progressivement les arrosages à l\'approche de la récolte. Récolter lorsque le feuillage jaunit puis commence à se coucher.',
        'propagation_notes' => null,
        'seed_production' => 'Conserver plusieurs beaux bulbes après récolte. Les hiverner puis les replanter. Laisser se développer les hampes florales. Lorsque les graines deviennent noires, couper les ombelles puis terminer le séchage à l\'abri.',
        'warnings' => null,
    ],
    [
        'species_name' => 'Chou-fleur',
        'cultivar_name' => 'Violet de Sicile',
        'cycle' => 'biennial',
        'germination_days_min' => 5,
        'germination_days_max' => 10,
        'spacing_cm_min' => 50,
        'spacing_cm_max' => 60,
        'harvest_days_min' => null,
        'harvest_days_max' => null,
        'essential_advice' => 'Culture longue. Sol riche et eau régulière. Éviter les stress hydriques. Faire plusieurs semis pour étaler les récoltes. Repiquage au stade 5–7 feuilles.',
        'propagation_notes' => null,
        'seed_production' => 'Conserver plusieurs beaux sujets. Les laisser poursuivre leur cycle jusqu\'à floraison. Récolter les siliques avant leur ouverture spontanée.',
        'warnings' => 'Brassica oleracea. Peut se croiser avec brocolis, autres choux-fleurs, choux cabus, kale et choux de Bruxelles.',
    ],
    [
        'species_name' => 'Figuier',
        'cultivar_name' => 'Figues blanches',
        'cycle' => 'perennial',
        'germination_days_min' => null,
        'germination_days_max' => null,
        'spacing_cm_min' => null,
        'spacing_cm_max' => null,
        'harvest_days_min' => null,
        'harvest_days_max' => null,
        'essential_advice' => 'Pour conserver fidèlement la variété, utiliser une multiplication végétative plutôt que le semis. Exposition chaude et ensoleillée. Arrosage attentif pendant l\'installation des jeunes plants.',
        'propagation_notes' => 'Bouture : fin hiver → printemps. Prélever un rameau ligneux sain d\'environ 20–30 cm. Installer dans un substrat drainant maintenu humide jusqu\'à l\'enracinement. Plantation préférable en automne ou printemps.',
        'seed_production' => 'Non applicable en pratique courante : le semis ne permet pas de reproduire fidèlement le pied mère. La multiplication se fait par bouture.',
        'warnings' => '« Figues blanches » est une désignation descriptive et non l\'identification certaine d\'un cultivar. À documenter : origine du plant, date d\'acquisition, prix, couleurs des fruits et de la chair, dates des récoltes, nombre de fructifications annuelles, photographies.',
    ],
];

$periods = [
    'Laitue' => [
        'sow' => [['start_month' => 2, 'end_month' => 9]],
        'bolt' => [['start_month' => 5, 'end_month' => 9]],
        'seed' => [['start_month' => 6, 'end_month' => 9]],
    ],
    'Mâche' => [
        'sow' => [['start_month' => 7, 'end_month' => 10]],
        'harvest' => [['start_month' => 9, 'end_month' => 3]],
        'bolt' => [['start_month' => 3, 'end_month' => 5]],
        'seed' => [['start_month' => 5, 'end_month' => 6]],
    ],
    'Navet' => [
        'sow' => [['start_month' => 2, 'end_month' => 4], ['start_month' => 7, 'end_month' => 9]],
        'harvest' => [['start_month' => 4, 'end_month' => 6], ['start_month' => 9, 'end_month' => 11]],
        'bolt' => [['start_month' => 3, 'end_month' => 5]],
        'seed' => [['start_month' => 5, 'end_month' => 7]],
    ],
    'Oignon' => [
        'sow' => [['start_month' => 2, 'end_month' => 4]],
        'harvest' => [['start_month' => 7, 'end_month' => 9]],
        'flowering' => [['start_month' => 4, 'end_month' => 7]],
        'seed' => [['start_month' => 6, 'end_month' => 8]],
    ],
    'Chou-fleur' => [
        'sow' => [['start_month' => 2, 'end_month' => 6]],
        'transplant' => [['start_month' => 3, 'end_month' => 7]],
        'harvest' => [['start_month' => 5, 'end_month' => 11]],
        'bolt' => [['start_month' => 5, 'end_month' => 8]],
        'seed' => [['start_month' => 6, 'end_month' => 9]],
    ],
    'Figuier' => [
        'cutting' => [['start_month' => 1, 'end_month' => 4]],
        'planting' => [['start_month' => 3, 'end_month' => 5], ['start_month' => 10, 'end_month' => 11]],
        'harvest' => [['start_month' => 7, 'end_month' => 10]],
    ],
];

foreach ($varieties as $v) {
    $id = $repo->upsert($v);
    $repo->clearPeriods($id);
    foreach ($periods[$v['species_name']] as $type => $ranges) {
        $repo->setPeriods($id, $type, $ranges);
    }
}

echo "OK\n";

<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use TaskFlow\Database;
use TaskFlow\SeedlingRepository;
use TaskFlow\VarietyRepository;

final class CulturesFeatureTest extends TestCase
{
    private static PDO $pdo;
    private static SeedlingRepository $seedlings;
    private static VarietyRepository $varieties;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = Database::get(__DIR__ . '/../data/taskflow.db');
        self::$seedlings = new SeedlingRepository(self::$pdo);
        self::$varieties = new VarietyRepository(self::$pdo);
    }

    public function testVarietiesSeeded(): void
    {
        $count = self::$pdo->query('SELECT COUNT(*) FROM varieties')->fetchColumn();
        $this->assertGreaterThanOrEqual(6, (int) $count, 'Au moins 6 varietes attendues');
    }

    public function testPeriodsExist(): void
    {
        $count = self::$pdo->query('SELECT COUNT(*) FROM variety_periods')->fetchColumn();
        $this->assertGreaterThanOrEqual(40, (int) $count, 'Periodes culturales manquantes');
    }

    public function testNavetDiscontinuousSow(): void
    {
        $id = self::$pdo->query("SELECT id FROM varieties WHERE species_name = 'Navet'")->fetchColumn();
        $periods = self::$varieties->allPeriods((int) $id);
        $sow = array_values(array_filter($periods, fn ($p) => $p['period_type'] === 'sow'));
        $months = [];
        foreach ($sow as $p) {
            $start = (int) $p['start_month'];
            $end = (int) $p['end_month'];
            do {
                $months[] = $start;
                if ($start === $end) break;
                $start = $start === 12 ? 1 : $start + 1;
            } while (true);
        }
        $months = array_unique($months);
        $this->assertContains(2, $months);
        $this->assertContains(3, $months);
        $this->assertContains(4, $months);
        $this->assertContains(7, $months);
        $this->assertContains(8, $months);
        $this->assertContains(9, $months);
    }

    public function testMacheWrapAroundHarvest(): void
    {
        $id = self::$pdo->query("SELECT id FROM varieties WHERE species_name = 'Mache'")->fetchColumn();
        $periods = self::$varieties->allPeriods((int) $id);
        $harvest = array_values(array_filter($periods, fn ($p) => $p['period_type'] === 'harvest'))[0] ?? null;
        $this->assertNotNull($harvest);
        $this->assertSame(9, (int) $harvest['start_month']);
        $this->assertSame(3, (int) $harvest['end_month']);
    }

    public function testSeedlingVarietyJoin(): void
    {
        $figId = self::$pdo->query("SELECT id FROM varieties WHERE species_name = 'Figuier'")->fetchColumn();
        $seedling = self::$seedlings->findById(1);
        $this->assertNotNull($seedling);
        $this->assertSame((int) $figId, (int) $seedling['variety_id']);
        $this->assertSame('Figuier', $seedling['species_name']);
    }

    public function testCrudCultureWithVariety(): void
    {
        $navetId = self::$pdo->query("SELECT id FROM varieties WHERE species_name = 'Navet'")->fetchColumn();
        $id = self::$seedlings->create([
            'name' => '__test_culture__',
            'variety_id' => $navetId,
            'variety' => '',
            'quantity' => 2,
            'seeded_at' => '2026-08-12',
            'repotted_at' => null,
            'location' => 'banc',
            'origin' => 'graines test',
            'note' => 'note test',
        ]);

        $found = self::$seedlings->findById($id);
        $this->assertNotNull($found);
        $this->assertSame('__test_culture__', $found['name']);
        $this->assertSame('Navet', $found['species_name']);

        self::$seedlings->update($id, array_merge($found, ['name' => '__test_culture_updated__']));
        $updated = self::$seedlings->findById($id);
        $this->assertSame('__test_culture_updated__', $updated['name']);

        self::$seedlings->delete($id);
        $this->assertNull(self::$seedlings->findById($id));
    }

    public function testWateringLogsUnchanged(): void
    {
        $count = self::$pdo->query('SELECT COUNT(*) FROM watering_logs')->fetchColumn();
        $this->assertGreaterThanOrEqual(1, (int) $count);
    }

    public function testDetailPageContainsVarietySheet(): void
    {
        $html = file_get_contents('http://127.0.0.1:8082/seedlings.php?id=1');
        $this->assertStringContainsString('culture-card', $html);
        $this->assertStringContainsString('Fiche variété', $html);
        $this->assertStringContainsString('Période', $html);
        $this->assertStringContainsString('menu-toggle', $html);
    }
}

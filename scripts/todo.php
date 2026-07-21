#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TaskFlow\Database;
use TaskFlow\TaskRepository;
use TaskFlow\DisciplineRepository;
use TaskFlow\ChecklistRepository;

$dbPath = $argv[1] ?? 'data/taskflow.db';
$action = $argv[2] ?? null;

if (!in_array($action, ['add-task','remove-task','done','undo','add-habit','remove-habit','log-habit','add-checklist','add-checklist-item','toggle-checklist-item','remove-checklist-item','remove-checklist','list','show'], true)) {
    fwrite(STDERR, "Usage: php scripts/todo.php [data/taskflow.db] <action> [args...]

Actions:
  add-task             <title> <category> [subcategory] [priority] [due_at]
  remove-task          <id>
  done                 <id>
  undo                 <id>
  add-habit            <type:corps|mental> <title> <target> <unit:reps|sessions> [step]
  remove-habit         <id>
  log-habit            <habit_id> <value> [YYYY-MM-DD]
  add-checklist        <YYYY-MM-DD> <title>
  add-checklist-item   <checklist_id> <label> [sort_order]
  toggle-checklist-item <item_id>
  remove-checklist-item <item_id>
  remove-checklist     <id>
  list                 [tasks|habits|checklists]
  show                 <id>
");
    exit(1);
}

backup($dbPath);

$pdo = Database::get($dbPath);
$taskRepo = new TaskRepository($pdo);
$discipline = new DisciplineRepository($pdo);
$checklists = new ChecklistRepository($pdo);

function backup(string $dbPath): void {
    $dir = dirname($dbPath);
    $base = basename($dbPath);
    $target = $dir . '/.' . $base . '.before.' . date('Ymd-His') . '.bak';
    copy($dbPath, $target);
    fwrite(STDERR, "Backup: $target\n");
}

try {
    match ($action) {
        'add-task' => addTask($taskRepo, array_slice($argv, 3)),
        'remove-task' => removeTask($taskRepo, (int) ($argv[3] ?? 0)),
        'done' => $taskRepo->markDone((int) ($argv[3] ?? 0)),
        'undo' => $taskRepo->restore((int) ($argv[3] ?? 0)),
        'add-habit' => addHabit($discipline, array_slice($argv, 3)),
        'remove-habit' => $discipline->deleteHabit((int) ($argv[3] ?? 0)),
        'log-habit' => logHabit($discipline, array_slice($argv, 3)),
        'add-checklist' => addChecklist($checklists, array_slice($argv, 3)),
        'add-checklist-item' => addChecklistItem($checklists, array_slice($argv, 3)),
        'toggle-checklist-item' => $checklists->toggleItem((int) ($argv[3] ?? 0)),
        'remove-checklist-item' => $checklists->deleteItem((int) ($argv[3] ?? 0)),
        'remove-checklist' => $checklists->deleteChecklist((int) ($argv[3] ?? 0)),
        'list' => listAll($taskRepo, $discipline, $checklists, $argv[3] ?? null),
        'show' => showTask($taskRepo, (int) ($argv[3] ?? 0)),
    };
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

function addTask(TaskRepository $repo, array $args): void {
    [$title, $category, $subcategory, $priority, $dueAt] = array_pad($args, 5, null);
    $data = [
        'title' => trim($title ?? ''),
        'category' => trim($category ?? ''),
        'subcategory' => $subcategory ? trim($subcategory) : null,
        'priority' => $priority !== null ? (int) $priority : null,
        'due_at' => $dueAt ? trim($dueAt) : null,
    ];
    $id = $repo->create($data);
    echo "Task created: $id\n";
}

function removeTask(TaskRepository $repo, int $id): void {
    $repo->delete($id);
    echo "Task removed: $id\n";
}

function addHabit(DisciplineRepository $repo, array $args): void {
    [$type, $title, $target, $unit, $step] = array_pad($args, 5, '1');
    $id = $repo->addHabit(trim($type ?? ''), trim($title ?? ''), (int) ($target ?? 1), trim($unit ?? ''), (int) ($step ?? 1));
    echo "Habit created: $id\n";
}

function logHabit(DisciplineRepository $repo, array $args): void {
    [$habitId, $value, $date] = array_pad($args, 3, null);
    $date = $date ? trim($date) : date('Y-m-d');
    $repo->log((int) $habitId, $date, (int) $value);
    echo "Logged " . ((int) $value) . " for habit $habitId on $date\n";
}

function addChecklist(ChecklistRepository $repo, array $args): void {
    [$date, $title] = array_pad($args, 2, null);
    $cl = $repo->findOrCreateForDate(trim($date ?? ''), trim($title ?? ''));
    echo "Checklist: {$cl['id']}\n";
}

function addChecklistItem(ChecklistRepository $repo, array $args): void {
    [$id, $label, $order] = array_pad($args, 3, '0');
    $repo->addItem((int) $id, trim($label ?? ''), (int) ($order ?? 0));
    echo "Item added to checklist $id\n";
}

function listAll(TaskRepository $tasks, DisciplineRepository $discipline, ChecklistRepository $checklists, ?string $scope): void {
    $scope = $scope ?? 'all';
    if (in_array($scope, ['all', 'tasks'], true)) {
        echo "\n## Tasks incomplete\n";
        foreach ($tasks->findIncomplete() as $row) {
            printf("%3d | %s | %s/%s | P%d | %s\n", $row['id'], $row['title'], $row['category'], $row['subcategory'] ?? '-', $row['priority'] ?? '-', $row['due_at'] ?? '?');
        }
    }
    if (in_array($scope, ['all', 'habits'], true)) {
        echo "\n## Habits\n";
        foreach ($discipline->listHabits() as $row) {
            printf("%3d | %s | %s | %d/%d %s\n", $row['id'], $row['type'], $row['title'], $row['today_current'], $row['target_value'], $row['unit']);
        }
    }
    if (in_array($scope, ['all', 'checklists'], true)) {
        echo "\n## Checklists\n";
        foreach ($checklists->listDates() as $row) {
            $done = count(array_filter($checklists->itemsFor((int) $row['id']), fn($i) => (int) $i['done'] === 1));
            $total = count($checklists->itemsFor((int) $row['id']));
            printf("%3d | %s | %s | %d/%d done\n", $row['id'], $row['checklist_date'], $row['title'], $done, $total);
        }
    }
}

function showTask(TaskRepository $repo, int $id): void {
    $task = $repo->find($id);
    if (!$task) {
        fwrite(STDERR, "Task not found: $id\n");
        return;
    }
    print_r($task);
}

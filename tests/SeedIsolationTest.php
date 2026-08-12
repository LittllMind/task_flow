<?php
require __DIR__ . '/../vendor/autoload.php';
use TaskFlow\Database;
use TaskFlow\TaskRepository;

// Fresh DB = default categories
$tmp = tempnam(sys_get_temp_dir(), 'tf_seed_');
unlink($tmp);
$repo = new TaskRepository(Database::get($tmp));
$cats = $repo->categories();
assert(isset($cats['Dev']), 'fresh has Dev');
assert(in_array('TaskFlow', $cats['Dev'], true), 'fresh has TaskFlow subcat');

// Add custom category, then new repo instance should not overwrite
$repo->addSubcategory('Custom', 'SubA');
$repo2 = new TaskRepository(Database::get($tmp));
$cats2 = $repo2->categories();
assert(isset($cats2['Custom']) && in_array('SubA', $cats2['Custom'], true), 'custom category preserved');
assert(!isset($cats2['Dev']), 'defaults not re-injected when categories exist');

unlink($tmp);
echo "Seed isolation OK
";

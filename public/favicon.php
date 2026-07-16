<?php
declare(strict_types=1);

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$color = str_contains($host, '10001mb.com') ? '#f43f5e' : '#22d3ee';
$svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>
  <rect width='100' height='100' rx='20' fill='{$color}'/>
  <text x='50' y='68' font-size='55' text-anchor='middle' fill='%230f172a'>✓</text>
</svg>";

header('Content-Type: image/svg+xml');
echo $svg;

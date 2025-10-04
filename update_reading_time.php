<?php
/**
 * Script to update reading_time for existing articles
 * Run with: php update_reading_time.php
 */

require __DIR__ . '/vendor/autoload.php';

// Load settings
$settings = require __DIR__ . '/src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/src/dependencies.php';

// Get the database instance
$container = $app->getContainer();
$db = $container->get('db');

use Conduit\Models\Article;

echo "Updating reading_time for all articles...\n\n";

// Get all articles
$articles = Article::all();

$updated = 0;
foreach ($articles as $article) {
    // Calculate reading time
    $wordCount = str_word_count(strip_tags($article->body));
    $readingTime = (int) ceil($wordCount / 200);
    
    // Update the article
    $article->reading_time = $readingTime;
    $article->save();
    
    $updated++;
    echo "Updated article '{$article->title}' - {$wordCount} words = {$readingTime} min\n";
}

echo "\n✓ Successfully updated {$updated} articles.\n";

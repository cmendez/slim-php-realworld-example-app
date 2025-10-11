<?php
/**
 * Script to recalculate popularity scores for all existing articles
 * Run this after running the migration to populate initial scores
 * 
 * Usage: php recalculate_popularity_scores.php
 */

require __DIR__ . '/vendor/autoload.php';

// Load settings
$settings = require __DIR__ . '/src/settings.php';

// Bootstrap Eloquent
$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection($settings['settings']['database']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

use Conduit\Models\Article;
use Conduit\Services\SentimentAnalysisService;
use Conduit\Services\PopularityService;

echo "Starting popularity score recalculation...\n\n";

// Create service instances
$sentimentAnalysis = new SentimentAnalysisService();
$popularityService = new PopularityService($sentimentAnalysis);

// Get all articles
$articles = Article::all();
$totalArticles = $articles->count();
$processed = 0;

echo "Found {$totalArticles} articles to process.\n\n";

foreach ($articles as $article) {
    $processed++;
    
    try {
        $score = $popularityService->recalculateScore($article);
        echo "[{$processed}/{$totalArticles}] Article '{$article->title}' - Score: {$score}\n";
    } catch (Exception $e) {
        echo "[{$processed}/{$totalArticles}] ERROR processing article '{$article->title}': {$e->getMessage()}\n";
    }
}

echo "\n✓ Recalculation complete! Processed {$processed} articles.\n";

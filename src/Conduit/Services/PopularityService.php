<?php

namespace Conduit\Services;

use Conduit\Models\Article;
use Conduit\Models\Comment;

/**
 * Service for managing article popularity scores
 * Handles real-time updates when favorites or comments change
 */
class PopularityService
{
    /**
     * Points per favorite
     */
    private const FAVORITE_POINTS = 2;

    /**
     * @var SentimentAnalysisService
     */
    private $sentimentAnalysis;

    /**
     * PopularityService constructor.
     * 
     * @param SentimentAnalysisService $sentimentAnalysis
     */
    public function __construct(SentimentAnalysisService $sentimentAnalysis)
    {
        $this->sentimentAnalysis = $sentimentAnalysis;
    }

    /**
     * Increment popularity score when a favorite is added
     * 
     * @param Article $article
     * @return void
     */
    public function incrementFavorite(Article $article): void
    {
        $article->increment('popularity_score', self::FAVORITE_POINTS);
    }

    /**
     * Decrement popularity score when a favorite is removed
     * 
     * @param Article $article
     * @return void
     */
    public function decrementFavorite(Article $article): void
    {
        $article->decrement('popularity_score', self::FAVORITE_POINTS);
    }

    /**
     * Add comment sentiment score to article popularity
     * 
     * @param Comment $comment
     * @return void
     */
    public function addCommentScore(Comment $comment): void
    {
        $score = $this->sentimentAnalysis->calculateScore($comment->body);
        $comment->article->increment('popularity_score', $score);
        
        // Store the score in the comment for later removal
        // We'll add a sentiment_score column to comments table
        $comment->sentiment_score = $score;
        $comment->saveQuietly(); // Save without triggering events
    }

    /**
     * Remove comment sentiment score from article popularity
     * 
     * @param Comment $comment
     * @return void
     */
    public function removeCommentScore(Comment $comment): void
    {
        // If sentiment_score was stored, use it; otherwise calculate it
        $score = $comment->sentiment_score ?? $this->sentimentAnalysis->calculateScore($comment->body);
        $comment->article->decrement('popularity_score', $score);
    }

    /**
     * Recalculate full popularity score for an article
     * Use this only when necessary (e.g., data migration or fixing inconsistencies)
     * 
     * @param Article $article
     * @return int The calculated score
     */
    public function recalculateScore(Article $article): int
    {
        $score = 0;
        
        // Add favorites score
        $favoritesCount = $article->favorites()->count();
        $score += $favoritesCount * self::FAVORITE_POINTS;
        
        // Add comments sentiment scores
        $comments = $article->comments()->get();
        foreach ($comments as $comment) {
            $commentScore = $this->sentimentAnalysis->calculateScore($comment->body);
            $score += $commentScore;
        }
        
        // Update article
        $article->popularity_score = $score;
        $article->save();
        
        return $score;
    }
}

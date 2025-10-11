<?php

namespace Conduit\Services;

/**
 * Service for calculating sentiment scores from comment text
 * Uses optimized O(log n) algorithm with sorted word lists
 */
class SentimentAnalysisService
{
    /**
     * Positive words sorted alphabetically for binary search
     * @var array
     */
    private const POSITIVE_WORDS = [
        'acierto',
        'bueno',
        'claro',
        'correcto',
        'excelente',
        'facil',
        'fantastico',
        'felicitaciones',
        'genial',
        'gracias',
        'impresionante',
        'increible',
        'interesante',
        'maravilloso',
        'mejora',
        'perfecto',
        'preciso',
        'recomendado',
        'util',
        'valioso',
    ];

    /**
     * Negative words sorted alphabetically for binary search
     * @var array
     */
    private const NEGATIVE_WORDS = [
        'error',
        'inutil',
        'malo',
        'odio',
        'pesimo',
    ];

    /**
     * Base score for any comment
     */
    private const BASE_SCORE = 1;

    /**
     * Points per positive word
     */
    private const POSITIVE_WORD_SCORE = 2;

    /**
     * Points per negative word (will be subtracted)
     */
    private const NEGATIVE_WORD_SCORE = 2;

    /**
     * Calculate sentiment score for a comment
     * 
     * @param string $commentBody
     * @return int The sentiment score
     */
    public function calculateScore(string $commentBody): int
    {
        $score = self::BASE_SCORE;
        
        // Normalize text: remove accents and convert to lowercase
        $normalizedText = $this->normalizeText($commentBody);
        
        // Extract words from text
        $words = $this->extractWords($normalizedText);
        
        // Count positive and negative words using binary search
        foreach ($words as $word) {
            if ($this->binarySearch(self::POSITIVE_WORDS, $word)) {
                $score += self::POSITIVE_WORD_SCORE;
            } elseif ($this->binarySearch(self::NEGATIVE_WORDS, $word)) {
                $score -= self::NEGATIVE_WORD_SCORE;
            }
        }
        
        return $score;
    }

    /**
     * Normalize text: remove accents and convert to lowercase
     * 
     * @param string $text
     * @return string
     */
    private function normalizeText(string $text): string
    {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');
        
        // Remove accents
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $text
        );
        
        return $text;
    }

    /**
     * Extract words from text (alphanumeric only)
     * 
     * @param string $text
     * @return array
     */
    private function extractWords(string $text): array
    {
        // Extract only alphanumeric words
        preg_match_all('/\b[a-z0-9]+\b/u', $text, $matches);
        return $matches[0] ?? [];
    }

    /**
     * Binary search in a sorted array - O(log n)
     * 
     * @param array $sortedArray
     * @param string $target
     * @return bool
     */
    private function binarySearch(array $sortedArray, string $target): bool
    {
        $left = 0;
        $right = count($sortedArray) - 1;
        
        while ($left <= $right) {
            $mid = (int) floor(($left + $right) / 2);
            $comparison = strcmp($sortedArray[$mid], $target);
            
            if ($comparison === 0) {
                return true;
            } elseif ($comparison < 0) {
                $left = $mid + 1;
            } else {
                $right = $mid - 1;
            }
        }
        
        return false;
    }
}

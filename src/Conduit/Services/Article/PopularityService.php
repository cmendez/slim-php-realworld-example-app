<?php

namespace Conduit\Services\Article;

use Conduit\Models\Article;

class PopularityService
{
    /**
     * Recalcula y guarda el puntaje de popularidad de un artículo.
     *
     * @param Article $article El artículo que se va a actualizar.
     */
    public function updateScore(Article $article): void
    {
        // --- 1) Puntos por Favoritos: +2 por cada uno ---
        $favoritesScore = $article->favorites()->count() * 2;

        // --- 2) Puntos por Comentarios: análisis de sentimiento ---
        $commentsScore = 0;
        $positivas = [
            'increible', 'excelente', 'bueno', 'util', 'genial', 'fantastico',
            'maravilloso', 'perfecto', 'impresionante', 'claro', 'preciso',
            'valioso', 'interesante', 'recomendado', 'gracias', 'felicitaciones',
            'mejora', 'acierto', 'facil', 'correcto'
        ];
        $negativas = [
            'malo', 'pesimo', 'inutil', 'error', 'odio', 'horrible', 'terrible',
            'desastre', 'decepcionante', 'incorrecto', 'falso', 'confuso',
            'equivocado', 'pobre', 'mediocre', 'basura', 'frustrante',
            'problema', 'dificil', 'lento', 'mala'
        ];

        $commentBodies = $article->comments()->pluck('body');

        foreach ($commentBodies as $body) {
            // +1 punto base por cada comentario
            $currentCommentScore = 1;
            
            // Normalizar texto para un análisis simple
            $normalizedText = strtolower(preg_replace('/[^a-z0-9\s]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $body ?? '')));
            $words = explode(' ', $normalizedText);

            foreach ($words as $word) {
                if (in_array($word, $positivas)) {
                    $currentCommentScore += 2; // Bonificación por palabra positiva
                } elseif (in_array($word, $negativas)) {
                    $currentCommentScore -= 2; // Penalización por palabra negativa
                }
            }
            $commentsScore += $currentCommentScore;
        }

        // --- 3) Guardar el puntaje total en el artículo ---
        $article->popularity_score = $favoritesScore + $commentsScore;
        $article->save();
    }
}
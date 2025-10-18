<?php

namespace Conduit\Services;

class CommentPopularityService
{
    private static $positiveWords = [
        'increíble', 'excelente', 'bueno', 'útil', 'genial', 'fantástico', 'maravilloso', 'perfecto', 'impresionante', 'claro', 'preciso', 'valioso', 'interesante', 'recomendado', 'gracias', 'felicitaciones', 'mejora', 'acierto', 'fácil', 'correcto'
    ];

    private static $negativeWords = [
        'malo', 'pésimo', 'inútil', 'error', 'odio', 'horrible', 'terrible', 'desastre', 'decepcionante', 'incorrecto', 'falso', 'confuso', 'equivocado', 'pobre', 'mediocre', 'basura', 'frustrante', 'problema', 'difícil', 'lento'
    ];

    public static function calculatePopularity($body)
    {
        $popularity = 1; // Punto base

        $bodyLower = mb_strtolower($body, 'UTF-8');

        // Remover tildes para comparación
        $bodyNormalized = self::normalizeText($bodyLower);

        $hasPositive = false;
        $hasNegative = false;

        foreach (self::$positiveWords as $word) {
            $wordNormalized = self::normalizeText($word);
            if (strpos($bodyNormalized, $wordNormalized) !== false) {
                $hasPositive = true;
                break;
            }
        }

        foreach (self::$negativeWords as $word) {
            $wordNormalized = self::normalizeText($word);
            if (strpos($bodyNormalized, $wordNormalized) !== false) {
                $hasNegative = true;
                break;
            }
        }

        if ($hasPositive) {
            $popularity += 2;
        }

        if ($hasNegative) {
            $popularity -= 2;
        }

        return $popularity;
    }

    private static function normalizeText($text)
    {
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ã' => 'a', 'õ' => 'o', 'ñ' => 'n'
        ];

        return strtr($text, $replacements);
    }
}

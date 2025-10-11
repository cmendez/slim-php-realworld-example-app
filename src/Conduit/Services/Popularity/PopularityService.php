<?php
namespace Conduit\Services\Popularity;

use Conduit\Models\Article;

class PopularityService
{
    /** @var string[] */
    private $positives = ['genial','excelente','bueno','útil','util','positivo','fantástico','fantastico','increíble','increible','me encanta'];
    /** @var string[] */
    private $negatives = ['malo','pésimo','pesimo','error','odio','negativo','horrible','terrible','inútil','inutil'];

    /** Normaliza texto: minúsculas, sin tildes ni signos. */
    private function normalize(string $t): string
    {
        $t = mb_strtolower($t, 'UTF-8');
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        $t = preg_replace('/[^a-z0-9\s]/', ' ', $t);
        return preg_replace('/\s+/', ' ', trim($t));
    }

    /** Puntaje de un comentario: +1 base +2 por positiva -2 por negativa */
    public function computeCommentScore(string $body): int
    {
        $text = $this->normalize($body);
        if ($text === '') return 1; // al menos base

        $words = explode(' ', $text);
        $posSet = array_flip($this->positives);
        $negSet = array_flip($this->negatives);

        $score = 1; // base
        foreach ($words as $w) {
            if (isset($posSet[$w])) $score += 2;
            if (isset($negSet[$w])) $score -= 2;
        }
        return $score;
    }

    /** +2 por favorite, -2 por unfavorite */
    public function applyFavoriteDelta(Article $article, int $delta): void
    {
        $article->increment('popularity_score', 2 * $delta);
        // ->save() no es necesario con increment()
    }

    /** Aplica delta directo (para comentarios) */
    public function applyCommentDelta(Article $article, int $delta): void
    {
        $article->increment('popularity_score', $delta);
    }
}

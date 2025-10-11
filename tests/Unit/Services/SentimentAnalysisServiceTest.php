<?php

namespace Tests\Unit\Services;

use Conduit\Services\SentimentAnalysisService;
use PHPUnit\Framework\TestCase;

class SentimentAnalysisServiceTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SentimentAnalysisService();
    }

    /**
     * @test
     */
    public function it_calculates_positive_strong_sentiment()
    {
        $comment = "Genial, genial. Un post excelente.";
        $score = $this->service->calculateScore($comment);
        
        // Base: +1, genial: +2, genial: +2, excelente: +2
        $this->assertEquals(7, $score);
    }

    /**
     * @test
     */
    public function it_calculates_positive_sentiment()
    {
        $comment = "¡Qué artículo tan bueno y útil!";
        $score = $this->service->calculateScore($comment);
        
        // Base: +1, bueno: +2, útil: +2
        $this->assertEquals(5, $score);
    }

    /**
     * @test
     */
    public function it_calculates_mixed_sentiment()
    {
        $comment = "El concepto es bueno, pero la explicación es un poco mala.";
        $score = $this->service->calculateScore($comment);
        
        // Base: +1, bueno: +2, malo: -2
        $this->assertEquals(1, $score);
    }

    /**
     * @test
     */
    public function it_calculates_neutral_sentiment()
    {
        $comment = "Gracias por compartir la información.";
        $score = $this->service->calculateScore($comment);
        
        // Base: +1, gracias: +2
        $this->assertEquals(3, $score);
    }

    /**
     * @test
     */
    public function it_calculates_negative_sentiment()
    {
        $comment = "Este ejemplo es malo y el código tiene un error.";
        $score = $this->service->calculateScore($comment);
        
        // Base: +1, malo: -2, error: -2
        $this->assertEquals(-3, $score);
    }

    /**
     * @test
     */
    public function it_calculates_negative_strong_sentiment()
    {
        $comment = "Odio este post, me parece pésimo e inútil.";
        $score = $this->service->calculateScore($comment);
        
        // Base: +1, odio: -2, pésimo: -2, inútil: -2
        $this->assertEquals(-5, $score);
    }

    /**
     * @test
     */
    public function it_normalizes_text_with_accents()
    {
        $comment = "Útil, fácil y práctico"; // Only útil and fácil are in positive list
        $score = $this->service->calculateScore($comment);
        
        // Base: +1, útil: +2, fácil: +2 (practico not in list)
        $this->assertEquals(5, $score);
    }

    /**
     * @test
     */
    public function it_handles_uppercase_text()
    {
        $comment = "EXCELENTE ARTÍCULO, MUY ÚTIL";
        $score = $this->service->calculateScore($comment);
        
        // Base: +1, excelente: +2, útil: +2
        $this->assertEquals(5, $score);
    }

    /**
     * @test
     */
    public function it_handles_repeated_words()
    {
        $comment = "Genial genial genial";
        $score = $this->service->calculateScore($comment);
        
        // Base: +1, genial: +2, genial: +2, genial: +2
        $this->assertEquals(7, $score);
    }

    /**
     * @test
     */
    public function it_handles_empty_comment()
    {
        $comment = "";
        $score = $this->service->calculateScore($comment);
        
        // Only base score
        $this->assertEquals(1, $score);
    }

    /**
     * @test
     */
    public function it_handles_comment_with_no_sentiment_words()
    {
        $comment = "Este es un comentario normal sin palabras especiales.";
        $score = $this->service->calculateScore($comment);
        
        // Only base score
        $this->assertEquals(1, $score);
    }

    /**
     * @test
     */
    public function it_ignores_partial_word_matches()
    {
        // "malograrse" contains "malo" but shouldn't match
        $comment = "El proceso puede malograrse si no sigues los pasos.";
        $score = $this->service->calculateScore($comment);
        
        // Should not match "malo" in "malograrse"
        // Only base score
        $this->assertEquals(1, $score);
    }

    /**
     * @test
     */
    public function it_handles_special_characters()
    {
        $comment = "¡Excelente! 100% útil. Muy, muy bueno...";
        $score = $this->service->calculateScore($comment);
        
        // Base: +1, excelente: +2, útil: +2, bueno: +2
        $this->assertEquals(7, $score);
    }

    /**
     * @test
     */
    public function it_calculates_complex_mixed_sentiment()
    {
        $comment = "El artículo es bueno e interesante, pero tiene errores. No es perfecto pero es útil.";
        $score = $this->service->calculateScore($comment);
        
        // Base: +1, bueno: +2, interesante: +2, error: -2, perfecto: +2, útil: +2
        $this->assertEquals(7, $score);
    }
}

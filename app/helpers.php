<?php

function analyzeSentiment($texto) {
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
            'problema', 'dificil', 'lento'
        ];

        // Normalizar texto a minúsculas y sin tildes
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú'],
            ['a', 'e', 'i', 'o', 'u'],
            $texto
        );
    $texto = str_replace(['.', ',', '!', '?', ';', ':'], '', $texto);
    $palabras = explode(' ', $texto);

    $puntaje = 1; // base
    foreach ($palabras as $p) {
            if (in_array($p, $positivas)) $puntaje += 2;
            if (in_array($p, $negativas)) $puntaje -= 2;
        }
        return $puntaje;
    
    
    // Determinar tipo
    if($puntaje >= 7) $tipo = 'Positivo Fuerte';
    elseif($puntaje >= 5) $tipo = 'Positivo';
    elseif($puntaje == 1) $tipo = 'Neutro';
    elseif($puntaje >= 1) $tipo = 'Mixto';
    elseif($puntaje >= -3) $tipo = 'Negativo';
    else $tipo = 'Negativo Fuerte';
    
    return ['score' => $puntaje, 'type' => $tipo];
}
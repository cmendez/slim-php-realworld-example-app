<?php

function analyzeSentiment($texto) {
    // Palabras positivas
    $positivas = [
        'increíble',
        'excelente',
        'bueno',
        'útil',
        'genial',
        'fantástico',
        'maravilloso',
        'perfecto',
        'impresionante',
        'claro',
        'preciso',
        'valioso',
        'interesante',
        'recomendado',
        'gracias',
        'felicitaciones',
        'mejora',
        'aprendo',
        'fácil',
        'correcto'
    ];
        
    // Palabras negativas
    $negativas = [
        'malo',
        'pésimo',
        'inútil',
        'error',
        'odio',
        'horrible',
        'terrible',
        'desastre',
        'decepcionante',
        'incorrecto',
        'falso',
        'confuso',
        'equivocado',
        'pobre',
        'mediocre',
        'basura',
        'irritante',
        'problema',
        'difícil',
        'lento'
    ];
    
    // Normalizar texto
    $texto = strtolower($texto);
    $texto = strtr($texto, ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u']);
    
    // Puntaje base
    $puntaje = 1;
    
    // Contar palabras
    foreach($positivas as $palabra) {
        $puntaje += substr_count($texto, $palabra) * 2;
    }
    
    foreach($negativas as $palabra) {
        $puntaje -= substr_count($texto, $palabra) * 2;
    }
    
    // Determinar tipo
    if($puntaje >= 7) $tipo = 'Positivo Fuerte';
    elseif($puntaje >= 3) $tipo = 'Positivo';
    elseif($puntaje == 1) $tipo = 'Neutro';
    elseif($puntaje >= -1) $tipo = 'Mixto';
    elseif($puntaje >= -3) $tipo = 'Negativo';
    else $tipo = 'Negativo Fuerte';
    
    return ['score' => $puntaje, 'type' => $tipo];
}
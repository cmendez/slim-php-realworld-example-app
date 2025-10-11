<?php

use Conduit\Models\Article;
use Conduit\Models\User;

$this->factory->define(Article::class, function (\Faker\Generator $faker) {
        $body = $faker->paragraphs(3, true);
        $conteo_de_palabras= str_word_count(strip_tags($body));
        $reading_time= ceil($conteo_de_palabras /200);

        return [
            'title'       => $title = $faker->sentence,
            'slug'        => str_slug($title),
            'description' => $faker->paragraph,
            'body'        => $body,
            'reading_time' => $reading_time,
            'user_id'   => function () {
                return $this->factory->of(User::class)->create()->id;
            },
        ];
    });


<?php

return [

    /*
    |--------------------------------------------------------------------
    | Entité par défaut
    |--------------------------------------------------------------------
    |
    | Slug de l'entité utilisée quand le contexte de la requête ne permet
    | de résoudre aucune autre entité (voir App\Support\CurrentEntity).
    | En V1, c'est la seule entité qui existe réellement.
    |
    */
    'default_entity_slug' => env('CONSULAT_DEFAULT_ENTITY_SLUG', 'congo-benin'),

];

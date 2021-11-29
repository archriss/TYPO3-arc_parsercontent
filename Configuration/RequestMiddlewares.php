<?php

return [
    'frontend' => [
        'archriss/arc-parsercontent/parser' => [
            'target' => \Archriss\ArcParsercontent\Middleware\Parser::class,
            'before' => [
                'typo3/cms-frontend/content-length-headers'
            ],
        ],
    ]
];
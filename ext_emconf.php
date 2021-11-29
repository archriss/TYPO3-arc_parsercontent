<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Arc Parsercontent',
    'description' => 'A simple parsercontent extension. No plugin, just a middleware parsing rte content',
    'category' => 'misc',
    'author' => '',
    'author_email' => '',
    'author_company' => 'Archriss',
    'state' => 'stable',
    'clearCacheOnLoad' => 0,
    'version' => '10.4',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-10.4.99',
            'mask' => '7.0.0-7.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];

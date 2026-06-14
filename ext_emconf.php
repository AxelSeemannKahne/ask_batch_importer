<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'ASK Batch Importer',
    'description' => 'Batch import of product data from Microsoft Business Central into configurable targets. CLI-driven, two-phase, resumable.',
    'category' => 'misc',
    'author' => 'Axel Seemann-Kahne',
    'author_email' => 'info@seemann-kahne.de',
    'author_company' => 'seemann-kahne.de',
    'state' => 'beta',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'php' => '8.2.0-8.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
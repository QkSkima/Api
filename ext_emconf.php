<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'QkSkima Api',
    'description' => 'A lightweight TYPO3 API middleware providing a clean, Extbase-free way to define REST-style endpoints under /api/* paths. Includes CSRF protection, frontend authentication support, and allows endpoint access from Fluid without plugins or TypoScript. Ideal for lean SPA-like applications, HTML-over-the-wire workflows, or lightweight headless-style integrations without a frontend build pipeline.',
    'category' => 'fe',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'QkSkima\\Api\\' => 'Classes',
        ],
    ],
    'state' => 'beta',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'Colin Atkins',
    'author_email' => 'atkins@hey.com',
    'author_company' => 'QkSkima Inc.',
    'version' => '0.1.0',
];

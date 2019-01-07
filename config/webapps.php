<?php

return [
    'Typo3' => [
        'ignoredFilenames' => ['index.html', '.htaccess'],
        'ignoredFolders' => ['install', 'dev', 't3lib'],
        'webroot' => '/typo3'
    ],
    'Joomla' => [
        'ignoredFilenames' => ['index.html', 'joomla.xml'],
        'ignoredFolders' => ['administrator', 'installation', 'libraries']
    ]
];

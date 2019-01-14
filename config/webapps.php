<?php

return [
    'Drupal' => [
        'ignoredFilenames' => ['index.html', '.htaccess'],
        'ignoredFolders' => ['#^vendor#'],
    ],
    'Wordpress' => [
        'ignoredFilenames' => ['index.html', '.htaccess'],
        'ignoredFolders' => [],
        'webroot' => '/wordpress'
    ],
    'Typo3' => [
        'ignoredFilenames' => ['index.html', '.htaccess'],
        'ignoredFolders' => ['#^install#', '#^dev#', '#^t3lib#', '#^build#', '#.*/Private/.*#', '#.*sysext/install.*#'],
        'webroot' => '/typo3'
    ],
    'Joomla' => [
        'ignoredFilenames' => ['index.html', 'joomla.xml', '.htaccess'],
        'ignoredFolders' => ['#^installation#', '#^libraries#', '#^build#']
    ]
];

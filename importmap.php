<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.ts',
        'entrypoint' => true,
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@symfony/ux-live-component' => [
        'path' => './vendor/symfony/ux-live-component/assets/dist/live_controller.js',
    ],
    '@enterprise-tooling-for-symfony/webui' => [
        'path' => './vendor/enterprise-tooling-for-symfony/webui-bundle/assets/index.js',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    'picocolors' => [
        'version' => '1.1.1',
    ],
    'mini-svg-data-uri' => [
        'version' => '1.4.4',
    ],
];

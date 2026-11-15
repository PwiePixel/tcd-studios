<?php
declare(strict_types=1);

defined('TCD_ROOT') || exit;

/**
 * Imprints that existed as static pages before the admin system. They are imported (as live, approved
 * revisions without an owner) when the database is created.
 */
$purpose = static function (string $name): string {
    return $name . ' is a content creator and streamer. TCD-Studio acts as management, providing support in digital media, marketing, and creative services.';
};

return [
    ['slug' => 'pixelcst', 'public' => ['display_name' => 'PixelCST', 'kind' => 'streamer', 'purpose' => $purpose('PixelCST'),
        'links' => [], 'show_contact_email' => false, 'contact_email' => '']],
    ['slug' => 'creepyx-470', 'public' => ['display_name' => 'CreepyX_470', 'kind' => 'streamer', 'purpose' => $purpose('CreepyX_470'),
        'links' => [['label' => '*.creepyx.com', 'url' => 'https://creepyx.com/']],
        'show_contact_email' => false, 'contact_email' => '']],
    ['slug' => 'tth-projects', 'public' => ['display_name' => 'TTH Projects', 'kind' => 'streamer', 'purpose' => $purpose('TTH Projects'),
        'links' => [
            ['label' => '*.dragonattack.de', 'url' => 'https://dragonattack.de/'],
            ['label' => '*.tth-projects.de', 'url' => 'https://tth-projects.de/'],
            ['label' => '*.tth-gaming.de', 'url' => 'https://tth-gaming.de/'],
            ['label' => '*.tth-admin.de', 'url' => 'https://tth-admin.de/'],
            ['label' => '*.tth-dev.de', 'url' => 'https://tth-dev.de/'],
        ],
        'show_contact_email' => false, 'contact_email' => '']],
];

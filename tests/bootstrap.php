<?php

$workspaceMappings = [
    'Ustal\\StreamHub\\SymfonyBridge\\' => dirname(__DIR__, 2) . '/stream-hub-symfony-bridge/src',
    'Ustal\\StreamHub\\Plugins\\' => dirname(__DIR__, 2) . '/stream-hub-plugins/src',
    'Ustal\\StreamHub\\' => dirname(__DIR__, 2) . '/stream-hub-core/src',
];

spl_autoload_register(static function (string $class) use ($workspaceMappings): void {
    foreach ($workspaceMappings as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix) || !is_dir($baseDir)) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = $baseDir . '/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($path)) {
            require $path;
        }
    }
}, true, true);

require dirname(__DIR__) . '/vendor/autoload.php';

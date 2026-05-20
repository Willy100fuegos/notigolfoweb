<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

function getSiteConfig() {
    $configFile = __DIR__ . '/../content/config.json';
    if (!file_exists($configFile)) {
        return [
            'social' => ['facebook' => '#', 'twitter' => '#', 'instagram' => '#'],
            'banners' => ['top' => '', 'sidebar1' => '', 'sidebar2' => '', 'bottom' => '']
        ];
    }
    return json_decode(file_get_contents($configFile), true);
}

function saveSiteConfig($data) {
    $configFile = __DIR__ . '/../content/config.json';
    file_put_contents($configFile, json_encode($data, JSON_PRETTY_PRINT));
}
?>

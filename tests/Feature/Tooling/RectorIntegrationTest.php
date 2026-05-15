<?php

use Rector\Configuration\RectorConfigBuilder;

it('registers rector scripts in composer', function (): void {
    $projectRoot = dirname(__DIR__, 3);
    $composerPath = $projectRoot.'/composer.json';
    $composerConfig = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);

    expect($composerConfig)->toBeArray();
    expect($composerConfig['scripts'])->toHaveKey('rector');
    expect($composerConfig['scripts'])->toHaveKey('rector:dry');
});

it('loads the rector configuration file', function (): void {
    $projectRoot = dirname(__DIR__, 3);
    $rectorConfigPath = $projectRoot.'/rector.php';

    expect($rectorConfigPath)->toBeFile();

    $rectorConfig = require $rectorConfigPath;

    expect($rectorConfig)->toBeInstanceOf(RectorConfigBuilder::class);
});

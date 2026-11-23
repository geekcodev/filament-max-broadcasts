<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Config;

use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class EnvVariablesTest extends TestCase
{
    public function testDefaultPermissionStringsFollowBridgeScheme(): void
    {
        self::assertSame('max_broadcasts.view', config('filament-max-broadcasts.permissions.view'));
        self::assertSame('max_broadcasts.create', config('filament-max-broadcasts.permissions.create'));
        self::assertSame('max_broadcasts.manage', config('filament-max-broadcasts.permissions.manage'));
    }

    public function testEnvExampleMatchesConfigEnvNames(): void
    {
        $root = dirname(__DIR__, 3);

        $config = (string) file_get_contents($root.'/config/filament-max-broadcasts.php');
        $example = (string) file_get_contents($root.'/.env.example');

        $configMatches = [];
        $exampleMatches = [];

        preg_match_all("/env\('([A-Z0-9_]+)'/", $config, $configMatches);
        preg_match_all('/^([A-Z0-9_]+)=/m', $example, $exampleMatches);

        $fromConfig = $configMatches[1];
        $fromExample = $exampleMatches[1];

        sort($fromConfig);
        sort($fromExample);

        self::assertSame($fromConfig, $fromExample);
    }
}

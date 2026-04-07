<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Unit\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Ustal\StreamHub\Component\Enum\DefaultSlotName;
use Ustal\StreamHub\Component\Service\PluginDefinitionBuilder;
use Ustal\StreamHub\Component\Service\PluginManager;
use Ustal\StreamHub\Core\Plugins\CoreStream\CoreStreamPlugin;
use Ustal\StreamHub\SymfonyBundle\CacheWarmer\StreamHubAssetsWarmer;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\FakeAssetPlugin;

final class StreamHubAssetsWarmerTest extends TestCase
{
    private string $targetDir;

    protected function setUp(): void
    {
        $this->targetDir = sys_get_temp_dir() . '/stream-hub-assets-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->targetDir)) {
            $this->removeDir($this->targetDir);
        }
    }

    public function testItCopiesAssetsForEnabledPluginsUsingNormalizedDirectories(): void
    {
        $registry = (new PluginDefinitionBuilder([CoreStreamPlugin::class]))->build(
            [FakeAssetPlugin::class],
            [DefaultSlotName::MAIN]
        );
        $manager = new PluginManager($registry);
        $warmer = new StreamHubAssetsWarmer($manager, targetPublicDir: $this->targetDir);

        $warmer->warmUp($this->targetDir);

        $this->assertFileExists($this->targetDir . '/plugins/core/js/stream-hub.js');
        $this->assertFileExists($this->targetDir . '/plugins/fake-plugin-name/js/fake-plugin.js');
        $this->assertFileExists($this->targetDir . '/plugins/fake-plugin-name/css/fake-plugin.css');
    }

    private function removeDir(string $directory): void
    {
        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDir($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}

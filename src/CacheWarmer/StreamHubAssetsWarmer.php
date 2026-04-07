<?php

namespace Ustal\StreamHub\SymfonyBundle\CacheWarmer;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Ustal\StreamHub\Component\Service\PluginManager;

final class StreamHubAssetsWarmer implements CacheWarmerInterface
{
    private const JS = 'js';
    private const CSS = 'css';

    public function __construct(
        private readonly PluginManager $pluginManager,
        private readonly Filesystem $filesystem = new Filesystem(),
        private readonly ?string $targetPublicDir = null,
    ) {}

    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $targetDir = $this->targetPublicDir ?? dirname(__DIR__) . '/Resources/public';
        $assets = $this->pluginManager->getPublicAssets();

        $this->filesystem->mkdir($targetDir);

        foreach ($assets as $pluginId => $assetConfig) {
            $pluginDir = $targetDir . '/plugins/' . $this->normalizePluginDirectory($pluginId);
            $this->filesystem->remove($pluginDir);

            $this->copyAssetList($assetConfig['class'], $assetConfig['js'], $pluginDir . '/' . self::JS);
            $this->copyAssetList($assetConfig['class'], $assetConfig['css'], $pluginDir . '/' . self::CSS);
        }

        return [];
    }

    /**
     * @param string[] $assets
     */
    private function copyAssetList(string $pluginClass, array $assets, string $targetDir): void
    {
        if ($assets === []) {
            return;
        }

        $packageRoot = $this->resolvePackageRoot($pluginClass);
        $this->filesystem->mkdir($targetDir);

        foreach ($assets as $asset) {
            $sourcePath = $packageRoot . '/' . ltrim($asset, '/');

            if (!is_file($sourcePath)) {
                throw new \RuntimeException(sprintf(
                    'Asset "%s" declared by plugin %s was not found at "%s".',
                    $asset,
                    $pluginClass,
                    $sourcePath
                ));
            }

            $this->filesystem->copy($sourcePath, $targetDir . '/' . basename($sourcePath), true);
        }
    }

    private function resolvePackageRoot(string $pluginClass): string
    {
        $reflection = new \ReflectionClass($pluginClass);
        $directory = dirname($reflection->getFileName());

        while ($directory !== dirname($directory)) {
            if (is_file($directory . '/composer.json')) {
                return $directory;
            }

            $directory = dirname($directory);
        }

        throw new \RuntimeException(sprintf(
            'Unable to resolve package root for plugin class %s.',
            $pluginClass
        ));
    }

    private function normalizePluginDirectory(string $pluginId): string
    {
        $normalized = strtolower($pluginId);
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'plugin';
    }
}

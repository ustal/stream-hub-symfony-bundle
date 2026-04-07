<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Fake;

use Ustal\StreamHub\Component\Plugin\AbstractStreamPlugin;
use Ustal\StreamHub\Component\Plugin\StreamPluginCSSInterface;
use Ustal\StreamHub\Component\Plugin\StreamPluginJSInterface;

final class FakeAssetPlugin extends AbstractStreamPlugin implements StreamPluginJSInterface, StreamPluginCSSInterface
{
    public const NAME = 'fake_plugin-name';

    public static function getJSFiles(): array
    {
        return [
            'tests/Fixtures/assets/fake-plugin.js',
        ];
    }

    public static function getCSSFiles(): array
    {
        return [
            'tests/Fixtures/assets/fake-plugin.css',
        ];
    }
}

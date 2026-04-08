<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Fake;

use Ustal\StreamHub\Component\Plugin\AbstractStreamPlugin;

final class FakeDebugPlugin extends AbstractStreamPlugin
{
    public const NAME = 'fake-debug-plugin';

    public static function getCommandHandlers(): array
    {
        return [FakeCommandHandler::class];
    }

    public static function getWidgets(): array
    {
        return [FakeRootWidget::class];
    }
}

<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Ustal\StreamHub\Component\Service\PluginDefinition;
use Ustal\StreamHub\Component\Service\PluginDefinitionRegistry;
use Ustal\StreamHub\Component\Service\PluginManager;
use Ustal\StreamHub\SymfonyBundle\Command\DebugPluginsCommand;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\FakeAssetPlugin;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\FakeCommandHandler;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\FakeDebugPlugin;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\FakeRootWidget;

final class DebugPluginsCommandTest extends TestCase
{
    public function testItPrintsEnabledPluginsWithAssetsAndClasses(): void
    {
        $registry = new PluginDefinitionRegistry();
        $registry->add(new PluginDefinition(
            id: FakeAssetPlugin::getName(),
            class: FakeAssetPlugin::class,
            handlerClasses: [],
            widgets: [],
            widgetClasses: [],
            isDefault: true,
        ));
        $registry->add(new PluginDefinition(
            id: FakeDebugPlugin::getName(),
            class: FakeDebugPlugin::class,
            handlerClasses: [FakeCommandHandler::class],
            widgets: [],
            widgetClasses: [FakeRootWidget::class],
        ));

        $tester = new CommandTester(new DebugPluginsCommand(new PluginManager($registry)));
        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Stream Hub Plugins', $display);
        self::assertStringContainsString('Enabled plugins: 2', $display);
        self::assertStringContainsString(FakeAssetPlugin::getName(), $display);
        self::assertStringContainsString('js: tests/Fixtures/assets/fake-plugin.js', $display);
        self::assertStringContainsString('css: tests/Fixtures/assets/fake-plugin.css', $display);
        self::assertStringContainsString(FakeDebugPlugin::class, $display);
        self::assertStringContainsString(FakeRootWidget::class, $display);
        self::assertStringContainsString(FakeCommandHandler::class, $display);
        self::assertStringContainsString('required', $display);
        self::assertStringContainsString('configured', $display);
    }
}

<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Ustal\StreamHub\Component\Context\StreamContextInterface;
use Ustal\StreamHub\Component\Service\PluginDefinitionRegistry;
use Ustal\StreamHub\Component\Service\PluginManager;
use Ustal\StreamHub\Component\Service\SlotTree;
use Ustal\StreamHub\Component\Storage\StreamBackendInterface;
use Ustal\StreamHub\Core\Command\CommandBusInterface;
use Ustal\StreamHub\Core\Command\ModelCommandBusInterface;
use Ustal\StreamHub\Core\Render\SlotRendererInterface;
use Ustal\StreamHub\Core\Render\StreamPageRendererInterface;
use Ustal\StreamHub\Plugins\DialogScaffold\DialogScaffoldPlugin;
use Ustal\StreamHub\Plugins\MessageComposer\Command\SendMessageCommandHandler;
use Ustal\StreamHub\Plugins\MessageComposer\MessageComposerPlugin;
use Ustal\StreamHub\Plugins\MessageComposer\Service\MessageEventFactory;
use Ustal\StreamHub\Plugins\SidebarScaffold\SidebarScaffoldPlugin;
use Ustal\StreamHub\SymfonyBundle\DependencyInjection\StreamHubExtension;
use Ustal\StreamHub\Plugins\TwoColumnLayout\TwoColumnLayoutPlugin;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\InMemoryBackend;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\InMemoryContext;

final class StreamHubExtensionTest extends TestCase
{
    public function testItLoadsDefaultConfigurationIntoContainer(): void
    {
        $container = new ContainerBuilder();
        $extension = new StreamHubExtension();

        $extension->load([], $container);

        $this->assertSame('stream-hub', $container->getParameter('stream_hub.assets.public_prefix'));
        $this->assertSame(
            [
                TwoColumnLayoutPlugin::class,
                SidebarScaffoldPlugin::class,
                DialogScaffoldPlugin::class,
            ],
            $container->getParameter('stream_hub.enabled_plugins')
        );
        $this->assertSame(['main'], $container->getParameter('stream_hub.root_slots'));
        $this->assertSame([], $container->getParameter('stream_hub.id_generators'));
    }

    public function testItLoadsRuntimeAliasesWhenBackendAndContextServicesAreConfigured(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.stream_backend', new Definition(InMemoryBackend::class));
        $container->setDefinition('app.stream_context', new Definition(InMemoryContext::class));

        (new StreamHubExtension())->load([[
            'backend_service' => 'app.stream_backend',
            'context_service' => 'app.stream_context',
        ]], $container);

        $this->assertTrue($container->hasAlias(StreamBackendInterface::class));
        $this->assertTrue($container->hasAlias(StreamContextInterface::class));
        $this->assertSame('%stream_hub.backend_service%', (string) $container->getAlias(StreamBackendInterface::class));
        $this->assertSame(
            'Ustal\\StreamHub\\SymfonyBundle\\Context\\ViewAwareStreamContext',
            (string) $container->getAlias(StreamContextInterface::class)
        );
        $this->assertTrue($container->hasDefinition(PluginDefinitionRegistry::class));
        $this->assertTrue($container->hasDefinition(SlotTree::class));
        $this->assertTrue($container->hasDefinition(PluginManager::class));
        $this->assertTrue($container->hasDefinition(CommandBusInterface::class));
        $this->assertTrue($container->hasAlias(ModelCommandBusInterface::class));
        $this->assertTrue($container->hasAlias(SlotRendererInterface::class));
        $this->assertTrue($container->hasAlias(StreamPageRendererInterface::class));
        $this->assertFalse($container->hasDefinition(MessageEventFactory::class));
        $this->assertFalse($container->hasDefinition(SendMessageCommandHandler::class));
    }

    public function testItRegistersMessageComposerServicesOnlyWhenPluginIsEnabled(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.stream_backend', new Definition(InMemoryBackend::class));
        $container->setDefinition('app.stream_context', new Definition(InMemoryContext::class));

        (new StreamHubExtension())->load([[
            'backend_service' => 'app.stream_backend',
            'context_service' => 'app.stream_context',
            'enabled_plugins' => [
                TwoColumnLayoutPlugin::class,
                DialogScaffoldPlugin::class,
                MessageComposerPlugin::class,
            ],
            'id_generators' => [
                MessageComposerPlugin::getName() => [
                    'event_id' => 'uuid_v7',
                ],
            ],
        ]], $container);

        $this->assertTrue($container->hasDefinition(MessageEventFactory::class));
        $this->assertTrue($container->hasDefinition(SendMessageCommandHandler::class));
        $this->assertTrue($container->hasAlias('stream_hub.identifier_generator.message-composer.event_id'));
        $this->assertSame(
            'stream_hub.identifier_generator.uuid_v7',
            (string) $container->getAlias('stream_hub.identifier_generator.message-composer.event_id')
        );
    }

    public function testItRejectsEnabledPluginsWithMissingIdentifierGeneratorConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires identifier generator "event_id"');

        $container = new ContainerBuilder();

        (new StreamHubExtension())->load([[
            'enabled_plugins' => [
                MessageComposerPlugin::class,
            ],
        ]], $container);
    }

    public function testItRejectsPartialRuntimeConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be configured together');

        (new StreamHubExtension())->load([[
            'backend_service' => 'app.stream_backend',
        ]], new ContainerBuilder());
    }
}

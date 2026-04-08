<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Ustal\StreamHub\Component\Storage\StreamBackendInterface;
use Ustal\StreamHub\Core\Command\CommandBusInterface;
use Ustal\StreamHub\Core\Command\ModelCommandBusInterface;
use Ustal\StreamHub\Plugins\MessageComposer\Command\SendMessageCommandHandler;
use Ustal\StreamHub\Plugins\MessageComposer\Service\MessageEventFactory;
use Ustal\StreamHub\SymfonyBundle\DependencyInjection\StreamHubExtension;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\InMemoryBackend;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\InMemoryContext;

final class StreamHubExtensionTest extends TestCase
{
    public function testItLoadsDefaultConfigurationIntoContainer(): void
    {
        $container = new ContainerBuilder();
        $extension = new StreamHubExtension();

        $extension->load([], $container);

        $this->assertSame([], $container->getParameter('stream_hub.id_generators'));
    }

    public function testItLoadsRuntimeServicesWhenBackendAndContextServicesAreConfigured(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.stream_backend', new Definition(InMemoryBackend::class));
        $container->setDefinition('app.stream_context', new Definition(InMemoryContext::class));

        (new StreamHubExtension())->load([[
            'backend_service' => 'app.stream_backend',
            'context_service' => 'app.stream_context',
        ]], $container);

        $this->assertTrue($container->hasAlias(CommandBusInterface::class) || $container->hasDefinition(CommandBusInterface::class));
        $this->assertTrue($container->hasAlias(ModelCommandBusInterface::class));
        $this->assertFalse($container->hasDefinition(MessageEventFactory::class));
        $this->assertFalse($container->hasDefinition(SendMessageCommandHandler::class));
    }

    public function testItRegistersMessageComposerServicesWhenGeneratorIsConfigured(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.stream_backend', new Definition(InMemoryBackend::class));
        $container->setDefinition('app.stream_context', new Definition(InMemoryContext::class));

        (new StreamHubExtension())->load([[
            'backend_service' => 'app.stream_backend',
            'context_service' => 'app.stream_context',
            'id_generators' => [
                'message-composer' => [
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

    public function testItRejectsIncompleteMessageComposerGeneratorConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires identifier generator "event_id"');

        $container = new ContainerBuilder();

        (new StreamHubExtension())->load([[
            'id_generators' => [
                'message-composer' => [],
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

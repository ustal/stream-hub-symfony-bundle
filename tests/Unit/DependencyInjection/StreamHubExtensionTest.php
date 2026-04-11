<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Ustal\StreamHub\Core\Command\CommandBusInterface;
use Ustal\StreamHub\Core\Command\GuardedCommandBus;
use Ustal\StreamHub\Core\Command\ModelCommandBusInterface;
use Ustal\StreamHub\Core\StreamHub;
use Ustal\StreamHub\Core\StreamHubInterface;
use Ustal\StreamHub\Plugins\MessageComposer\Command\SendMessageCommandHandler;
use Ustal\StreamHub\Plugins\MessageComposer\Service\MessageEventFactory;
use Ustal\StreamHub\Plugins\StreamLifecycle\Command\StartStreamCommand;
use Ustal\StreamHub\Plugins\StreamLifecycle\Command\JoinStreamCommandHandler;
use Ustal\StreamHub\Plugins\StreamLifecycle\Command\LeaveStreamCommandHandler;
use Ustal\StreamHub\Plugins\StreamLifecycle\Command\StartStreamCommandHandler;
use Ustal\StreamHub\Plugins\StreamLifecycle\Service\LifecycleSystemEventFactory;
use Ustal\StreamHub\SymfonyBundle\DependencyInjection\StreamHubExtension;
use Ustal\StreamHub\SymfonyBundle\Registry\StreamHubRegistry;
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
        $this->assertTrue($container->hasDefinition(StreamHubRegistry::class));
        $this->assertFalse($container->hasAlias(StreamHubInterface::class));
    }

    public function testLegacyRootConfigurationRegistersDefaultInstanceAndLegacyAliases(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.stream_backend', new Definition(InMemoryBackend::class));
        $container->setDefinition('app.stream_context', new Definition(InMemoryContext::class));

        (new StreamHubExtension())->load([[
            'backend_service' => 'app.stream_backend',
            'context_service' => 'app.stream_context',
        ]], $container);

        $this->assertTrue($container->hasDefinition('stream_hub.instance.default.command_bus.inner'));
        $this->assertTrue($container->hasDefinition('stream_hub.instance.default.command_bus'));
        $this->assertTrue($container->hasDefinition('stream_hub.instance.default.model_command_bus'));
        $this->assertTrue($container->hasDefinition('stream_hub.instance.default.stream_hub'));
        $this->assertTrue($container->hasAlias(CommandBusInterface::class));
        $this->assertTrue($container->hasAlias(ModelCommandBusInterface::class));
        $this->assertTrue($container->hasAlias(StreamHubInterface::class));
        $this->assertTrue($container->hasAlias(StreamHub::class));
        $this->assertSame(
            GuardedCommandBus::class,
            $container->getDefinition('stream_hub.instance.default.command_bus')->getClass()
        );
        $this->assertSame(
            'stream_hub.instance.default.stream_hub',
            (string) $container->getAlias(StreamHubInterface::class)
        );
        $this->assertFalse($container->hasDefinition(MessageEventFactory::class));
        $this->assertFalse($container->hasDefinition(SendMessageCommandHandler::class));
        $this->assertFalse($container->hasDefinition(LifecycleSystemEventFactory::class));
        $this->assertFalse($container->hasDefinition(StartStreamCommandHandler::class));
        $this->assertFalse($container->hasDefinition(JoinStreamCommandHandler::class));
        $this->assertFalse($container->hasDefinition(LeaveStreamCommandHandler::class));
    }

    public function testItRegistersNamedInstancesWithoutOverwritingLegacyAliases(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.default_backend', (new Definition(InMemoryBackend::class))->setPublic(true));
        $container->setDefinition('app.default_context', (new Definition(InMemoryContext::class))->setPublic(true));
        $container->setDefinition('app.audit_backend', (new Definition(InMemoryBackend::class))->setPublic(true));
        $container->setDefinition('app.audit_context', (new Definition(InMemoryContext::class))->setPublic(true));

        (new StreamHubExtension())->load([[
            'backend_service' => 'app.default_backend',
            'context_service' => 'app.default_context',
            'instances' => [
                'audit' => [
                    'backend_service' => 'app.audit_backend',
                    'context_service' => 'app.audit_context',
                ],
            ],
        ]], $container);

        $this->assertTrue($container->hasDefinition('stream_hub.instance.default.stream_hub'));
        $this->assertTrue($container->hasDefinition('stream_hub.instance.audit.stream_hub'));
        $this->assertSame(
            'stream_hub.instance.default.stream_hub',
            (string) $container->getAlias(StreamHubInterface::class)
        );
    }

    public function testNamedInstancesStayIsolatedByBackendAndState(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.default_backend', (new Definition(InMemoryBackend::class))->setPublic(true));
        $container->setDefinition('app.default_context', (new Definition(InMemoryContext::class))
            ->setPublic(true)
            ->setArguments(['user-1', ['display_name' => 'Alice']]));
        $container->setDefinition('app.audit_backend', (new Definition(InMemoryBackend::class))->setPublic(true));
        $container->setDefinition('app.audit_context', (new Definition(InMemoryContext::class))
            ->setPublic(true)
            ->setArguments(['user-2', ['display_name' => 'Bob']]));

        (new StreamHubExtension())->load([[
            'backend_service' => 'app.default_backend',
            'context_service' => 'app.default_context',
            'id_generators' => [
                'stream-lifecycle' => [
                    'system_event_id' => 'uuid_v7',
                ],
            ],
            'instances' => [
                'audit' => [
                    'backend_service' => 'app.audit_backend',
                    'context_service' => 'app.audit_context',
                    'id_generators' => [
                        'stream-lifecycle' => [
                            'system_event_id' => 'uuid_v7',
                        ],
                    ],
                ],
            ],
        ]], $container);

        $container->getDefinition(StreamHubRegistry::class)->setPublic(true);
        $container->compile();

        /** @var StreamHubRegistry $registry */
        $registry = $container->get(StreamHubRegistry::class);
        $registry->get()->dispatch(new StartStreamCommand(contextId: 'default-stream'));
        $registry->get('audit')->dispatch(new StartStreamCommand(contextId: 'audit-stream'));

        /** @var InMemoryBackend $defaultBackend */
        $defaultBackend = $container->get('app.default_backend');
        /** @var InMemoryBackend $auditBackend */
        $auditBackend = $container->get('app.audit_backend');

        $this->assertSame(1, $defaultBackend->streamCount());
        $this->assertSame(1, $auditBackend->streamCount());
        $this->assertNotNull($defaultBackend->getStream(new InMemoryContext('user-1'), 'default-stream'));
        $this->assertNull($defaultBackend->getStream(new InMemoryContext('user-1'), 'audit-stream'));
        $this->assertNotNull($auditBackend->getStream(new InMemoryContext('user-2'), 'audit-stream'));
        $this->assertNull($auditBackend->getStream(new InMemoryContext('user-2'), 'default-stream'));
        $this->assertSame(1, $defaultBackend->eventCountFor('default-stream'));
        $this->assertSame(1, $auditBackend->eventCountFor('audit-stream'));
    }

    public function testItRegistersMessageComposerServicesForDefaultInstanceWhenGeneratorIsConfigured(): void
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

        $this->assertTrue($container->hasAlias(MessageEventFactory::class));
        $this->assertTrue($container->hasAlias(SendMessageCommandHandler::class));
        $this->assertTrue($container->hasAlias('stream_hub.identifier_generator.message-composer.event_id'));
        $this->assertSame(
            'stream_hub.instance.default.identifier_generator.message-composer.event_id',
            (string) $container->getAlias('stream_hub.identifier_generator.message-composer.event_id')
        );
    }

    public function testItRegistersStreamLifecycleServicesForNamedInstanceWhenGeneratorIsConfigured(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.stream_backend', new Definition(InMemoryBackend::class));
        $container->setDefinition('app.stream_context', new Definition(InMemoryContext::class));

        (new StreamHubExtension())->load([[
            'instances' => [
                'audit' => [
                    'backend_service' => 'app.stream_backend',
                    'context_service' => 'app.stream_context',
                    'id_generators' => [
                        'stream-lifecycle' => [
                            'system_event_id' => 'uuid_v7',
                        ],
                    ],
                ],
            ],
        ]], $container);

        $this->assertTrue($container->hasDefinition('stream_hub.instance.audit.stream_lifecycle.lifecycle_system_event_factory'));
        $this->assertTrue($container->hasDefinition('stream_hub.instance.audit.stream_lifecycle.start_stream_handler'));
        $this->assertTrue($container->hasDefinition('stream_hub.instance.audit.stream_lifecycle.join_stream_handler'));
        $this->assertTrue($container->hasDefinition('stream_hub.instance.audit.stream_lifecycle.leave_stream_handler'));
        $this->assertTrue($container->hasAlias('stream_hub.instance.audit.identifier_generator.stream-lifecycle.system_event_id'));
        $this->assertSame(
            'stream_hub.identifier_generator.uuid_v7',
            (string) $container->getAlias('stream_hub.instance.audit.identifier_generator.stream-lifecycle.system_event_id')
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

    public function testItRejectsIncompleteStreamLifecycleGeneratorConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires identifier generator "system_event_id"');

        $container = new ContainerBuilder();

        (new StreamHubExtension())->load([[
            'instances' => [
                'audit' => [
                    'id_generators' => [
                        'stream-lifecycle' => [],
                    ],
                ],
            ],
        ]], $container);
    }

    public function testItRejectsPartialRuntimeConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must configure both backend_service and context_service together');

        (new StreamHubExtension())->load([[
            'backend_service' => 'app.stream_backend',
        ]], new ContainerBuilder());
    }

    public function testItRejectsDoubleDefaultConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be configured both at the root level');

        (new StreamHubExtension())->load([[
            'backend_service' => 'app.stream_backend',
            'context_service' => 'app.stream_context',
            'instances' => [
                'default' => [
                    'backend_service' => 'app.other_backend',
                    'context_service' => 'app.other_context',
                ],
            ],
        ]], new ContainerBuilder());
    }
}

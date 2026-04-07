<?php

namespace Ustal\StreamHub\SymfonyBundle\Factory;

use Ustal\StreamHub\Component\Enum\DefaultSlotName;
use Ustal\StreamHub\Component\Service\PluginDefinitionBuilder;
use Ustal\StreamHub\Component\Service\PluginDefinitionRegistry;
use Ustal\StreamHub\Component\Service\PluginManager;
use Ustal\StreamHub\Component\Service\SlotTree;
use Ustal\StreamHub\Component\Service\SlotTreeBuilder;
use Ustal\StreamHub\Core\Command\CommandBusFactory;
use Ustal\StreamHub\Core\Command\CommandBusInterface;

final class StreamHubRuntimeFactory
{
    /**
     * @param list<class-string> $enabledPlugins
     * @param list<string> $rootSlots
     */
    public function buildPluginRegistry(
        PluginDefinitionBuilder $builder,
        array $enabledPlugins,
        array $rootSlots,
    ): PluginDefinitionRegistry {
        return $builder->build($enabledPlugins, $this->resolveRootSlots($rootSlots));
    }

    /**
     * @param list<string> $rootSlots
     */
    public function buildSlotTree(
        PluginDefinitionRegistry $registry,
        array $rootSlots,
    ): SlotTree {
        return (new SlotTreeBuilder())->build($registry, $this->resolveRootSlots($rootSlots));
    }

    public function buildPluginManager(PluginDefinitionRegistry $registry): PluginManager
    {
        return new PluginManager($registry);
    }

    public function buildCommandBus(
        CommandBusFactory $factory,
        PluginDefinitionRegistry $registry,
        iterable $handlers,
    ): CommandBusInterface {
        return $factory->create($registry, $handlers);
    }

    /**
     * @param list<string> $rootSlots
     * @return list<\BackedEnum>
     */
    private function resolveRootSlots(array $rootSlots): array
    {
        $resolved = [];

        foreach ($rootSlots as $slot) {
            $resolved[] = match ($slot) {
                DefaultSlotName::MAIN->value => DefaultSlotName::MAIN,
                default => throw new \InvalidArgumentException(sprintf(
                    'Unsupported root slot "%s". Register it in %s when needed.',
                    $slot,
                    self::class
                )),
            };
        }

        return $resolved;
    }
}

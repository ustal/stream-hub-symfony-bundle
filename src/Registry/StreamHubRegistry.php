<?php

namespace Ustal\StreamHub\SymfonyBundle\Registry;

use Psr\Container\ContainerInterface;
use Ustal\StreamHub\Core\StreamHubInterface;

final readonly class StreamHubRegistry
{
    /**
     * @param list<string> $instances
     */
    public function __construct(
        private ContainerInterface $locator,
        private array $instances,
    ) {}

    public function get(string $name = 'default'): StreamHubInterface
    {
        if (!$this->locator->has($name)) {
            throw new \InvalidArgumentException(sprintf('Stream Hub instance "%s" is not configured.', $name));
        }

        /** @var StreamHubInterface $streamHub */
        $streamHub = $this->locator->get($name);

        return $streamHub;
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return $this->instances;
    }

    public function has(string $name): bool
    {
        return $this->locator->has($name);
    }
}

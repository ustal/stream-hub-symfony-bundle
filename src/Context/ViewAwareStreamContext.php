<?php

namespace Ustal\StreamHub\SymfonyBundle\Context;

use Ustal\StreamHub\Component\Context\StreamContextInterface;
use Ustal\StreamHub\Component\Render\ViewRendererInterface;
use Ustal\StreamHub\Component\Render\WidgetTemplateResolverInterface;

final readonly class ViewAwareStreamContext implements StreamContextInterface
{
    public function __construct(
        private StreamContextInterface $inner,
        private ViewRendererInterface $viewRenderer,
        private ?WidgetTemplateResolverInterface $templateResolver = null,
    ) {}

    public function getUserId(): string
    {
        return $this->inner->getUserId();
    }

    public function getActor(): ?string
    {
        return $this->inner->getActor();
    }

    public function generateUrl(string $name, array $parameters = []): string
    {
        return $this->inner->generateUrl($name, $parameters);
    }

    public function getCsrfToken(string $intention): ?string
    {
        return $this->inner->getCsrfToken($intention);
    }

    public function has(string $key): bool
    {
        return $key === ViewRendererInterface::class
            || ($key === WidgetTemplateResolverInterface::class && $this->templateResolver !== null)
            || $this->inner->has($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === ViewRendererInterface::class) {
            return $this->viewRenderer;
        }

        if ($key === WidgetTemplateResolverInterface::class) {
            return $this->templateResolver ?? $default;
        }

        return $this->inner->get($key, $default);
    }
}

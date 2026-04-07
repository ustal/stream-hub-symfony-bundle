<?php

namespace Ustal\StreamHub\SymfonyBundle\Render;

use Psr\Container\ContainerInterface;
use Ustal\StreamHub\Component\Context\StreamContextInterface;
use Ustal\StreamHub\Component\Model\Stream;
use Ustal\StreamHub\Component\Render\RenderResult;
use Ustal\StreamHub\Component\Render\WidgetRenderAdapterInterface;
use Ustal\StreamHub\Component\Widget\StreamWidgetInterface;

final readonly class ContainerWidgetRenderAdapter implements WidgetRenderAdapterInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function render(string $widgetClass, Stream $stream, StreamContextInterface $context): RenderResult
    {
        $widget = $this->container->has($widgetClass) ? $this->container->get($widgetClass) : new $widgetClass();

        if (!$widget instanceof StreamWidgetInterface) {
            throw new \LogicException(sprintf(
                'Widget service %s must implement %s.',
                $widgetClass,
                StreamWidgetInterface::class
            ));
        }

        return $widget->render($context);
    }
}

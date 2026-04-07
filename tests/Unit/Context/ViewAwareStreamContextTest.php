<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Ustal\StreamHub\Component\Render\ViewRendererInterface;
use Ustal\StreamHub\Component\Render\WidgetTemplateResolverInterface;
use Ustal\StreamHub\SymfonyBundle\Context\ViewAwareStreamContext;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\InMemoryContext;
use Ustal\StreamHub\SymfonyBridge\Twig\TwigViewRenderer;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ViewAwareStreamContextTest extends TestCase
{
    public function testItExposesInjectedViewRendererViaContextGet(): void
    {
        $renderer = new TwigViewRenderer(new Environment(new ArrayLoader()));
        $context = new ViewAwareStreamContext(new InMemoryContext(), $renderer);

        $this->assertTrue($context->has(ViewRendererInterface::class));
        $this->assertSame($renderer, $context->get(ViewRendererInterface::class));
        $this->assertFalse($context->has(WidgetTemplateResolverInterface::class));
        $this->assertSame('user-1', $context->getUserId());
    }
}

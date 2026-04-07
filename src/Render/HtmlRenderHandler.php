<?php

namespace Ustal\StreamHub\SymfonyBundle\Render;

use Ustal\StreamHub\Component\Render\HtmlRenderResult;
use Ustal\StreamHub\Component\Render\RenderHandlerInterface;
use Ustal\StreamHub\Component\Render\RenderResult;

final class HtmlRenderHandler implements RenderHandlerInterface
{
    public function supports(RenderResult $result): bool
    {
        return $result instanceof HtmlRenderResult;
    }

    public function render(RenderResult $result): string
    {
        if (!$result instanceof HtmlRenderResult) {
            throw new \LogicException('Unexpected render result.');
        }

        return $result->html;
    }
}

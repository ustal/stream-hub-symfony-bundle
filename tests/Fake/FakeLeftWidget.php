<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Fake;

use Ustal\StreamHub\Component\Context\StreamContextInterface;
use Ustal\StreamHub\Component\Render\HtmlRenderResult;
use Ustal\StreamHub\Component\Widget\AbstractStreamWidget;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\Enum\FakeSlot;

final class FakeLeftWidget extends AbstractStreamWidget
{
    public static function getSlot(): \BackedEnum
    {
        return FakeSlot::LEFT;
    }

    public static function getName(): string
    {
        return 'fake_left_widget';
    }

    public static function supports(StreamContextInterface $context): bool
    {
        return true;
    }

    public function isVisible(StreamContextInterface $context): bool
    {
        return true;
    }

    public function render(StreamContextInterface $context): HtmlRenderResult
    {
        return new HtmlRenderResult('<div>fake-left</div>');
    }
}

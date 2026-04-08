<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Fake;

use Ustal\StreamHub\Component\Context\StreamContextInterface;
use Ustal\StreamHub\Component\Render\HtmlRenderResult;
use Ustal\StreamHub\Component\ValueObject\LayoutSlot;
use Ustal\StreamHub\Component\Widget\AbstractStreamWidget;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\Enum\FakeSlot;

final class FakeRootWidget extends AbstractStreamWidget
{
    public static function getSlot(): \BackedEnum
    {
        return FakeSlot::ROOT;
    }

    public static function getName(): string
    {
        return 'fake_root_widget';
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
        return new HtmlRenderResult('<div>fake-root</div>');
    }

    public static function provideSlots(): array
    {
        return [
            new LayoutSlot(FakeSlot::LEFT, \Ustal\StreamHub\Component\Enum\SlotAcceptanceMode::ANY),
            new LayoutSlot(FakeSlot::RIGHT, \Ustal\StreamHub\Component\Enum\SlotAcceptanceMode::ANY),
        ];
    }
}

<?php

namespace Ustal\StreamHub\SymfonyBundle\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Ustal\StreamHub\Component\Enum\WidgetPlacementMode;
use Ustal\StreamHub\Component\Service\SlotAssignment;
use Ustal\StreamHub\Component\Service\SlotTree;
use Ustal\StreamHub\Component\ValueObject\LayoutSlot;
use Ustal\StreamHub\SymfonyBundle\Command\DebugSlotsCommand;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\Enum\FakeSlot;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\FakeLeftWidget;
use Ustal\StreamHub\SymfonyBundle\Tests\Fake\FakeRootWidget;

final class DebugSlotsCommandTest extends TestCase
{
    public function testItPrintsSlotTreeFromRootSlots(): void
    {
        $slotTree = new SlotTree(
            slots: [
                FakeSlot::ROOT->value => true,
                FakeSlot::LEFT->value => true,
                FakeSlot::RIGHT->value => true,
            ],
            assignmentsBySlot: [
                FakeSlot::ROOT->value => [
                    new SlotAssignment(
                        pluginId: 'fake-debug-plugin',
                        widgetClass: FakeRootWidget::class,
                        targetSlot: FakeSlot::ROOT->value,
                        placementMode: WidgetPlacementMode::REPLACE,
                        providedSlots: [
                            new LayoutSlot(FakeSlot::LEFT, \Ustal\StreamHub\Component\Enum\SlotAcceptanceMode::ANY),
                            new LayoutSlot(FakeSlot::RIGHT, \Ustal\StreamHub\Component\Enum\SlotAcceptanceMode::ANY),
                        ],
                    ),
                ],
                FakeSlot::LEFT->value => [
                    new SlotAssignment(
                        pluginId: 'fake-left-plugin',
                        widgetClass: FakeLeftWidget::class,
                        targetSlot: FakeSlot::LEFT->value,
                        placementMode: WidgetPlacementMode::APPEND,
                        providedSlots: [],
                    ),
                ],
            ],
            childSlotsBySlot: [
                FakeSlot::ROOT->value => [FakeSlot::LEFT->value, FakeSlot::RIGHT->value],
            ],
        );

        $tester = new CommandTester(new DebugSlotsCommand($slotTree, [FakeSlot::ROOT->value]));
        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Stream Hub Slots', $display);
        self::assertStringContainsString(FakeSlot::ROOT->value, $display);
        self::assertStringContainsString('[replace] ' . FakeRootWidget::class . ' (plugin: fake-debug-plugin)', $display);
        self::assertStringContainsString(FakeSlot::LEFT->value, $display);
        self::assertStringContainsString('[append] ' . FakeLeftWidget::class . ' (plugin: fake-left-plugin)', $display);
        self::assertStringContainsString(FakeSlot::RIGHT->value, $display);
    }
}

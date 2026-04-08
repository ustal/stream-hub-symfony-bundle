<?php

namespace Ustal\StreamHub\SymfonyBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Ustal\StreamHub\Component\Service\SlotAssignment;
use Ustal\StreamHub\Component\Service\SlotTree;

#[AsCommand(name: 'stream-hub:debug:slots', description: 'Show the Stream Hub slot tree.')]
final class DebugSlotsCommand extends Command
{
    /**
     * @param string[] $rootSlots
     */
    public function __construct(
        private readonly SlotTree $slotTree,
        private readonly array $rootSlots,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Stream Hub Slots');

        $visited = [];

        foreach ($this->rootSlots as $rootSlot) {
            foreach ($this->renderSlot($rootSlot, 0, $visited) as $line) {
                $io->writeln($line);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, true> $visited
     * @return string[]
     */
    private function renderSlot(string $slot, int $depth, array &$visited): array
    {
        $indent = str_repeat('  ', $depth);
        $lines = [$indent . $slot];

        if (isset($visited[$slot])) {
            $lines[] = $indent . '  (already visited)';

            return $lines;
        }

        $visited[$slot] = true;

        foreach ($this->slotTree->getAssignmentsForSlot($slot) as $assignment) {
            $lines[] = $this->formatAssignment($assignment, $depth + 1);

            foreach ($assignment->providedSlots as $providedSlot) {
                $lines = array_merge(
                    $lines,
                    $this->renderSlot($providedSlot->getLayoutSlot()->value, $depth + 2, $visited)
                );
            }
        }

        return $lines;
    }

    private function formatAssignment(SlotAssignment $assignment, int $depth): string
    {
        $indent = str_repeat('  ', $depth);

        return sprintf(
            '%s[%s] %s (plugin: %s)',
            $indent,
            $assignment->placementMode->value,
            $assignment->widgetClass,
            $assignment->pluginId,
        );
    }
}

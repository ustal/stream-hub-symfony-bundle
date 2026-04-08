<?php

namespace Ustal\StreamHub\SymfonyBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Ustal\StreamHub\Component\Service\PluginManager;

#[AsCommand(name: 'stream-hub:debug:plugins', description: 'Show enabled Stream Hub plugins.')]
final class DebugPluginsCommand extends Command
{
    public function __construct(private readonly PluginManager $pluginManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $plugins = $this->pluginManager->getPlugins();
        $assetsByPlugin = $this->pluginManager->getPublicAssets();

        $io->title('Stream Hub Plugins');
        $io->text(sprintf('Enabled plugins: %d', count($plugins)));

        $table = new Table($output);
        $table->setHeaders(['Plugin', 'Class', 'Widgets', 'Handlers', 'Assets', 'Flags']);

        foreach ($plugins as $plugin) {
            $assets = $assetsByPlugin[$plugin->id] ?? ['js' => [], 'css' => []];

            $table->addRow([
                $plugin->id,
                $plugin->class,
                $this->formatList($plugin->widgetClasses),
                $this->formatList($plugin->handlerClasses),
                $this->formatAssets($assets['js'], $assets['css']),
                $plugin->isDefault ? 'required' : 'configured',
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    /**
     * @param string[] $items
     */
    private function formatList(array $items): string
    {
        if ($items === []) {
            return '-';
        }

        return implode("\n", $items);
    }

    /**
     * @param string[] $js
     * @param string[] $css
     */
    private function formatAssets(array $js, array $css): string
    {
        $lines = [];

        foreach ($js as $file) {
            $lines[] = sprintf('js: %s', $file);
        }

        foreach ($css as $file) {
            $lines[] = sprintf('css: %s', $file);
        }

        return $lines === [] ? '-' : implode("\n", $lines);
    }
}

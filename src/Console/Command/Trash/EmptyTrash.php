<?php

/**
 * The cdn:trash:empty console command
 *
 * @package  Nails
 * @category Console
 */

namespace Nails\Cdn\Console\Command\Trash;

use Nails\Cdn\Housekeeping\Trash;
use Nails\Components;
use Nails\Config;
use Nails\Console\Command\Base;
use Nails\Factory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @deprecated Use housekeeping:run --routine=Nails\Cdn\Housekeeping\Trash
 */
class EmptyTrash extends Base
{
    /**
     * Configure the cdn:trash:empty command
     */
    protected function configure()
    {
        $iRetention = (int) Config::get('CDN_TRASH_RETENTION', 180);

        $this
            ->setName('cdn:trash:empty')
            ->setDescription('[DEPRECATED] Deletes items which have been in the trash for ' . $iRetention . ' days')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Log what would be deleted without deleting'
            );
    }

    /**
     * Execute the command
     *
     * @param InputInterface  $oInput  The Input Interface provided by Symfony
     * @param OutputInterface $oOutput The Output Interface provided by Symfony
     *
     * @return int
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput)
    {
        parent::execute($oInput, $oOutput);

        $this->banner('CDN: Trash: Empty (deprecated)');

        if (!Components::exists('nails/module-housekeeping')) {
            $oOutput->writeln('<error>This command now requires nails/module-housekeeping.</error>');
            $oOutput->writeln('Install it with <comment>composer require nails/module-housekeeping</comment>');
            $oOutput->writeln('then run <comment>nails housekeeping:run --routine=' . Trash::class . '</comment>');

            return static::EXIT_CODE_FAILURE;
        }

        /** @var \Nails\Housekeeping\Service\Orchestrator $oOrchestrator */
        $oOrchestrator = Factory::service('Orchestrator', 'nails/module-housekeeping');
        $oResult       = $oOrchestrator->runRoutine(
            Trash::class,
            (bool) $oInput->getOption('dry-run'),
            true,
            $oOutput
        );

        return $oResult->isSuccess() ? static::EXIT_CODE_SUCCESS : static::EXIT_CODE_FAILURE;
    }
}

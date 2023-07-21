<?php

namespace Nails\Cdn\Console\Command\Monitor;

use Nails\Cdn\Constants;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Model\CdnObject;
use Nails\Cdn\Service\Cdn;
use Nails\Common\Helper\Model\Select;
use Nails\Common\Service\Database;
use Nails\Common\Service\FileCache;
use Nails\Console\Command\Base;
use Nails\Console\Exception\ConsoleException;
use Nails\Factory;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Unused
 *
 * @package Nails\Cdn\Console\Command
 */
class Unused extends Base
{
    const CACHE_FILE      = 'cdn-monitor-unsed.txt';
    const PROGRESS_FORMAT = '%%current%%/%%max%% [%%bar%%] %%percent:3s%%%% %%elapsed:6s%% / %%estimated:-6s%% %%memory:6s%% (found %s unused items)';

    // --------------------------------------------------------------------------

    /**
     * Configures the command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('cdn:monitor:unused')
            ->setDescription('Finds objects which are not being used')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the scan to run even if one is already running')
            ->addOption('reset', 'r', InputOption::VALUE_NONE, 'Resets the scan, allowing it to run again');
    }

    // --------------------------------------------------------------------------

    /**
     * Executes the app
     *
     * @param InputInterface  $oInput  The Input Interface provided by Symfony
     * @param OutputInterface $oOutput The Output Interface provided by Symfony
     *
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput)
    {
        parent::execute($oInput, $oOutput);

        // --------------------------------------------------------------------------

        /** @var FileCache $oFileCache */
        $oFileCache = Factory::service('FileCache');
        /** @var \Nails\Cdn\Service\Monitor $oService */
        $oService = Factory::service('Monitor', Constants::MODULE_SLUG);
        /** @var CdnObject $oCdn */
        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);
        /** @var Database $oDb */
        $oDb = Factory::service('Database');

        // --------------------------------------------------------------------------

        $sCacheFile = $oFileCache->getDir() . static::CACHE_FILE;

        // --------------------------------------------------------------------------

        if ($oInput->getOption('reset') || $oInput->getOption('force')) {

            $this->markAsRunning(false);
            if (file_exists($sCacheFile)) {
                unlink($sCacheFile);
            }

            if ($oInput->getOption('reset')) {
                $oOutput->writeln('<comment>Scan reset</comment>');
                return static::EXIT_CODE_SUCCESS;
            }
        }

        // --------------------------------------------------------------------------

        if (appSetting('cdn:monitor:unused:running', Constants::MODULE_SLUG)) {
            throw new ConsoleException(
                'A scan is already running. Please wait for it to complete before starting another.'
            );
        }

        $this->markAsRunning(true);

        // --------------------------------------------------------------------------

        $rCacheFile = fopen($sCacheFile, 'w');

        $oOutput->writeln('');
        $oOutput->writeln('Writing scan results to: <info>' . $sCacheFile . '</info>');
        fwrite($rCacheFile, 'BEGIN: ' . time() . PHP_EOL);

        // --------------------------------------------------------------------------

        $iNumUnsed = 0;

        $oOutput->writeln('');
        $oOutput->writeln('Scanning objects...');
        $oProgressBar = new ProgressBar($oOutput, $oObjectModel->countAll());
        $oProgressBar->setFormat(sprintf(self::PROGRESS_FORMAT, $iNumUnsed));
        $oProgressBar->start();

        $iStart = microtime(true);
        $oQuery = $oObjectModel->getAllRawQuery([new Select(['id'])]);

        while ($oResult = $oQuery->unbuffered_row()) {

            $oObject    = $oObjectModel->getById($oResult->id);
            $aLocations = $oService->locate($oObject);

            if (empty($aLocations)) {
                fwrite($rCacheFile, $oObject->id . PHP_EOL);
                $iNumUnsed++;
            }

            //  Clean up potential memory leaks
            unset($aLocations);
            $oObjectModel->clearCache();
            $oDb->flushCache();

            $oProgressBar->setFormat(sprintf(self::PROGRESS_FORMAT, $iNumUnsed));
            $oProgressBar->advance();
        }

        fclose($rCacheFile);
        $iEnd = microtime(true);
        $oProgressBar->finish();
        $oOutput->writeln('');
        $oOutput->writeln(sprintf(
            '<comment>Complete!</comment> Job took %s seconds',
            number_format($iEnd - $iStart, 2)
        ));
        $oOutput->writeln('');

        $this->markAsRunning(false);

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    private function markAsRunning(bool $bRunning): void
    {
        setAppSetting('cdn:monitor:unused:running', Constants::MODULE_SLUG, $bRunning);
    }
}

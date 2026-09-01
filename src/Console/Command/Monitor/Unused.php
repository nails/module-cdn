<?php

namespace Nails\Cdn\Console\Command\Monitor;

use DateMalformedStringException;
use DateTime;
use Nails\Cdn\Cdn\MetaData\SystemKey;
use Nails\Cdn\Constants;
use Nails\Cdn\Model;
use Nails\Cdn\Resource;
use Nails\Cdn\Service;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Helper\Model\Expand;
use Nails\Common\Helper\Model\Select;
use Nails\Common\Service\Database;
use Nails\Console\Command\Base;
use Nails\Console\Exception\ConsoleException;
use Nails\Factory;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class Unused
 *
 * @package Nails\Cdn\Console\Command
 */
class Unused extends Base
{
    const string PROGRESS_FORMAT = '%%current%%/%%max%% [%%bar%%] %%percent:3s%%%% %%elapsed:6s%% / %%estimated:-6s%% %%memory:6s%% (found %s unused items)';

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
     * @throws ConsoleException
     * @throws FactoryException
     * @throws ModelException
     * @throws Throwable
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput)
    {
        parent::execute($oInput, $oOutput);

        // --------------------------------------------------------------------------

        /** @var Service\Monitor $oService */
        $oService = Factory::service('Monitor', Constants::MODULE_SLUG);
        /** @var Model\CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);
        /** @var Database $oDb */
        $oDb = Factory::service('Database');

        // --------------------------------------------------------------------------

        $this->clearLastError();

        // --------------------------------------------------------------------------

        if ($oInput->getOption('reset') || $oInput->getOption('force')) {

            $this->markAsRunning(false);

            if ($oInput->getOption('reset')) {
                $oOutput->writeln('<comment>Scan reset</comment>');
                return static::EXIT_CODE_SUCCESS;
            }
        }

        // --------------------------------------------------------------------------

        if (self::isRunning()) {
            throw new ConsoleException(
                'A scan is already running. Please wait for it to complete before starting another.'
            );
        }

        $this->markAsRunning(true);

        // --------------------------------------------------------------------------

        $fnGetMetaValue = function (array $aMetadata, string $sKey): ?string {
            foreach ($aMetadata as $oItem) {
                if ($oItem->key === $sKey) {
                    return $oItem->value;
                }
            }
            return null;
        };

        $sSystemKeyUnused      = (new SystemKey\Unused)->get();
        $sSystemKeyUnusedSince = (new SystemKey\UnusedSince)->get();

        $fnStripUnusedMeta = function (array $aMetadata) use ($sSystemKeyUnused, $sSystemKeyUnusedSince): array {
            return array_values(array_filter(
                $aMetadata,
                fn($oItem) => !in_array($oItem->key, [
                    $sSystemKeyUnused,
                    $sSystemKeyUnusedSince,
                ])
            ));
        };

        // --------------------------------------------------------------------------

        try {

            $iNumUnused = 0;

            $oOutput->writeln('');
            $oOutput->writeln('Scanning objects...');
            $oProgressBar = new ProgressBar($oOutput, $oObjectModel->countAll());
            $oProgressBar->setFormat(sprintf(self::PROGRESS_FORMAT, $iNumUnused));
            $oProgressBar->start();

            $iStart = microtime(true);
            $oQuery = $oObjectModel->getAllRawQuery([new Select(['id'])]);

            while ($oResult = $oQuery->unbuffered_row()) {

                /** @var Resource\CdnObject $oObject */
                $oObject    = $oObjectModel->getById($oResult->id, [new Expand('bucket')]);
                $aLocations = $oService->locate($oObject);

                $aCurrentMeta    = (array) $oObject->metadata;
                $bAlreadyFlagged = $fnGetMetaValue($aCurrentMeta, $sSystemKeyUnused) !== null;

                if (empty($aLocations)) {

                    if (!$bAlreadyFlagged) {
                        $sSince     = $fnGetMetaValue($aCurrentMeta, $sSystemKeyUnusedSince);
                        $aNewMeta   = $fnStripUnusedMeta($aCurrentMeta);
                        $aNewMeta[] = (object) [
                            'key'   => $sSystemKeyUnused,
                            'value' => '1',
                        ];
                        $aNewMeta[] = (object) [
                            'key'   => $sSystemKeyUnusedSince,
                            'value' => $sSince ?? date('c'),
                        ];
                        $oObjectModel->update($oObject->id, ['metadata' => json_encode($aNewMeta)]);
                    }

                    $iNumUnused++;

                } elseif ($bAlreadyFlagged) {
                    $aNewMeta = $fnStripUnusedMeta($aCurrentMeta);
                    $oObjectModel
                        ->skipUpdateTimestamp()
                        ->skipUpdateUsers()
                        ->update(
                            $oObject->id,
                            ['metadata' => json_encode($aNewMeta)]
                        );
                }

                //  Clean up potential memory leaks
                unset($aLocations);
                $oObjectModel->clearCache();
                $oDb->flushCache();

                $oProgressBar->setFormat(sprintf(self::PROGRESS_FORMAT, $iNumUnused));
                $oProgressBar->advance();
            }

            $iEnd = microtime(true);
            $oProgressBar->finish();
            $oOutput->writeln('');
            $oOutput->writeln(sprintf(
                '<comment>Complete!</comment> Job took %s seconds',
                number_format($iEnd - $iStart, 2)
            ));
            $oOutput->writeln('');

        } catch (Throwable $e) {

            $this->setLastError($e->getMessage());
            throw $e;

        } finally {
            $this->markAsRunning(false);
        }

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     */
    public static function isRunning(): bool
    {
        return (bool) appSetting('cdn:monitor:unused:running', Constants::MODULE_SLUG, null, true);
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     * @throws DateMalformedStringException
     */
    public static function lastStartedAt(): ?DateTime
    {
        $sLastStarted = appSetting('cdn:monitor:unused:started', Constants::MODULE_SLUG);
        if (empty($sLastStarted)) {
            return null;
        }
        return new DateTime($sLastStarted);
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     */
    private function markAsRunning(bool $bRunning): void
    {
        setAppSetting('cdn:monitor:unused:running', Constants::MODULE_SLUG, $bRunning);
        if ($bRunning) {
            /** @var DateTime $oNow */
            $oNow = Factory::factory('DateTime');
            setAppSetting('cdn:monitor:unused:started', Constants::MODULE_SLUG, $oNow->format('c'));
        }
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     */
    private function clearLastError(): void
    {
        setAppSetting('cdn:monitor:unused:lasterror', Constants::MODULE_SLUG, null);
    }

    // --------------------------------------------------------------------------

    /**
     * @throws FactoryException
     */
    private function setLastError(string $error): void
    {
        setAppSetting('cdn:monitor:unused:lasterror', Constants::MODULE_SLUG, $error);
    }
}

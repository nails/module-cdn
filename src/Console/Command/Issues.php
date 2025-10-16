<?php

namespace Nails\Cdn\Console\Command;

use Nails\Cdn\Constants;
use Nails\Cdn\Interfaces\Driver;
use Nails\Cdn\Model\CdnObject;
use Nails\Cdn\Service\StorageDriver;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Factory\Logger;
use Nails\Common\Helper\Model\Expand;
use Nails\Common\Helper\Model\Select;
use Nails\Common\Helper\Model\Sort;
use Nails\Common\Helper\Model\WhereIn;
use Nails\Console\Command\Base;
use Nails\Factory;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Fix
 *
 * @package Nails\Cdn\Console\Command
 */
class Issues extends Base
{
    protected int                  $iIssuesFound        = 0;
    protected int                  $iIssuesFixed        = 0;
    protected int                  $iIssuesFixedFailure = 0;
    protected Logger               $oLogger;
    protected ConsoleSectionOutput $oSectionProgress;
    protected ConsoleSectionOutput $oSectionIssuesFound;
    protected ConsoleSectionOutput $oSectionIssuesFixed;

    // --------------------------------------------------------------------------

    /**
     * Configures the command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('cdn:issues')
            ->setDescription('Scans the CDN for issues')
            ->addOption('fix', 'f', InputOption::VALUE_NONE, 'Attempt to fix issues')
            ->addOption('objects', 'o', InputOption::VALUE_REQUIRED, 'Restrict to specific list of objects (CSV)');
    }

    // --------------------------------------------------------------------------

    /**
     * Executes the app
     *
     * @param InputInterface  $oInput  The Input Interface provided by Symfony
     * @param OutputInterface $oOutput The Output Interface provided by Symfony
     *
     * @return int
     * @throws FactoryException
     * @throws ModelException
     * @throws NailsException
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput)
    {
        parent::execute($oInput, $oOutput);

        // --------------------------------------------------------------------------

        /** @var StorageDriver $oStorageDriver */
        $oStorageDriver = Factory::service('StorageDriver', Constants::MODULE_SLUG);
        /** @var CdnObject $oObjectModel */
        $oObjectModel  = Factory::model('Object', Constants::MODULE_SLUG);
        $this->oLogger = Factory::factory('Logger');

        /** @var \DateTime $oNow */
        $oNow = Factory::factory('DateTime');
        $this->oLogger->setFile('cdn-issues-' . $oNow->format('Y-m-d-H-i-s') . '.php');

        // --------------------------------------------------------------------------

        $this->banner('CDN: Detect issues');
        $oOutput->writeln(sprintf(
            'Logging issues to: <comment>%s%s</comment>',
            $this->oLogger->getDir(),
            $this->oLogger->getFile()
        ));
        $oOutput->writeln('');

        $bAttemptFix = $oInput->getOption('fix');
        $aObjectIds  = array_filter(explode(',', (string) $oInput->getOption('objects')));

        $iTotalObjects = empty($aObjectIds)
            ? $oObjectModel->countAll()
            : $oObjectModel->countAll([new WhereIn('id', $aObjectIds)]);

        $this->oSectionProgress = $oOutput->section();

        $this->oSectionIssuesFound = $oOutput->section();
        $this->oSectionIssuesFound->writeln('');
        $this->oSectionIssuesFound->writeln($this->iIssuesFound . ' issues detected');

        if ($bAttemptFix) {
            $this->oSectionIssuesFixed = $oOutput->section();
            $this->oSectionIssuesFixed->writeln($this->iIssuesFixed . ' issues fixed');
        }

        // --------------------------------------------------------------------------

        //  Progress bar
        $oProgress = $oProgress = new ProgressBar($this->oSectionProgress, $iTotalObjects);
        $oProgress->setFormat('debug');

        //  Prepare the loop data
        $oQuery = $oObjectModel->getAllRawQuery(array_filter([
            new Select(['id']),
            new Sort('id', Sort::ASC),
            empty($aObjectIds)
                ? null
                : new WhereIn('id', $aObjectIds),
        ]));

        $oProgress->start();
        while ($oObject = $oQuery->unbuffered_row()) {

            /** @var \Nails\Cdn\Resource\CdnObject $oObject */
            $oObject = $oObjectModel->getById($oObject->id, [new Expand('bucket')]);
            /** @var Driver $oDriver */
            $oDriver = $oStorageDriver->getInstance($oObject->driver);

            if ($oDriver->objectExists($oObject->file->name->disk, $oObject->bucket->slug)) {

                $aMetaDataErrors = $oDriver->getObjectMetaDataErrors(
                    $oObject->file->name->disk,
                    $oObject->file->name->human,
                    $oObject->bucket->slug,
                    $oObject->file->mime
                );

                if (!empty($aMetaDataErrors)) {

                    foreach ($aMetaDataErrors as $sMetaDataError) {
                        $this->addIssue(
                            $oObject,
                            sprintf('Metadata error: %s', $sMetaDataError)
                        );
                    }

                    if ($bAttemptFix) {

                        $bResult = $oDriver->fixObjectMetaDataErrors(
                            $oObject->file->name->disk,
                            $oObject->file->name->human,
                            $oObject->bucket->slug,
                            $oObject->file->mime
                        );

                        if ($bResult) {
                            $this->addFix($oObject, 'Metadata errors fixed');
                        } else {
                            $this->addFixFailure($oObject, 'Failed to fix metadata errors');
                        }
                    }
                }

            } else {
                $this->addIssue($oObject, 'Does not exist in storage');
            }

            $oProgress->advance();
        }
        $oProgress->finish();

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * Records a new issue which was found, and updates the terminal
     *
     * @param \Nails\Cdn\Resource\CdnObject $oObject
     * @param string                        $sMessage
     */
    private function addIssue(\Nails\Cdn\Resource\CdnObject $oObject, string $sMessage): void
    {
        $this->iIssuesFound++;
        $this->oLogger->info(sprintf('Object #%s (%s): %s', $oObject->id, $oObject->driver, $sMessage));

        $this->oSectionIssuesFound->clear();
        $this->oSectionIssuesFound->writeln('');
        $this->oSectionIssuesFound->writeln(sprintf('%d issues detected', $this->iIssuesFound));
    }

    // --------------------------------------------------------------------------

    /**
     * Records a new fix which was completed, and updates the terminal
     *
     * @param \Nails\Cdn\Resource\CdnObject $oObject
     * @param string                        $sMessage
     */
    private function addFix(\Nails\Cdn\Resource\CdnObject $oObject, string $sMessage): void
    {
        $this->iIssuesFixed++;
        $this->oLogger->info(sprintf('Object #%s (%s): %s', $oObject->id, $oObject->driver, $sMessage));

        $this->oSectionIssuesFixed->clear();
        $this->oSectionIssuesFixed->writeln(
            $this->iIssuesFixedFailure
                ? sprintf('%d issues fixed (%d fix failures)', $this->iIssuesFixed, $this->iIssuesFixedFailure)
                : sprintf('%d issues fixed', $this->iIssuesFixed)
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Records a fix which failed, and updates the terminal
     *
     * @param \Nails\Cdn\Resource\CdnObject $oObject
     * @param string                        $sMessage
     */
    private function addFixFailure(\Nails\Cdn\Resource\CdnObject $oObject, string $sMessage): void
    {
        $this->iIssuesFixedFailure++;
        $this->oLogger->info(sprintf('Object #%s (%s): %s', $oObject->id, $oObject->driver, $sMessage));

        $this->oSectionIssuesFixed->clear();
        $this->oSectionIssuesFixed->writeln(sprintf('%d issues fixed (%d fix failures)', $this->iIssuesFixed, $this->iIssuesFixedFailure));
    }
}

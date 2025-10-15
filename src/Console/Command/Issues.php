<?php

namespace Nails\Cdn\Console\Command;

use Nails\Cdn\Constants;
use Nails\Cdn\Interfaces\Driver;
use Nails\Cdn\Model\CdnObject;
use Nails\Cdn\Service\StorageDriver;
use Nails\Common\Helper\Model\Expand;
use Nails\Common\Helper\Model\Select;
use Nails\Common\Helper\Model\Sort;
use Nails\Console\Command\Base;
use Nails\Factory;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Fix
 *
 * @package Nails\Cdn\Console\Command
 */
class Issues extends Base
{
    /**
     * Stores driver instances
     *
     * @var array
     */
    protected $aDriverInstances = [];

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
            ->setDescription('Scans the CDN for issues');
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

        $this->banner('CDN: Detect issues');

        // --------------------------------------------------------------------------

        /** @var StorageDriver $oStorageDriver */
        $oStorageDriver = Factory::service('StorageDriver', Constants::MODULE_SLUG);
        /** @var CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Constants::MODULE_SLUG);

        // --------------------------------------------------------------------------

        $iTotalObjects = $oObjectModel->countAll();

        //  Progress bar
        $oSectionProgress = $oOutput->section();
        $oProgress        = $oProgress = new ProgressBar($oSectionProgress, $iTotalObjects);
        $oProgress->setFormat('debug');
        $oSectionProgress->writeln('<comment>Progress</comment>:');

        //  Issues section
        $oSectionIssues = $oOutput->section();
        $oSectionIssues->writeln('');
        $oSectionIssues->writeln('<comment>Issues</comment>:');
        $oSectionIssues->writeln('No issues detected');
        $aObjectsWithIssues = [];

        $oQuery = $oObjectModel->getAllRawQuery([
            new Select(['id']),
            new Sort('id', Sort::ASC),
        ]);

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
                            $oSectionIssues,
                            $oObject,
                            sprintf('Metadata error: %s', $sMetaDataError),
                            $aObjectsWithIssues
                        );
                    }
                }

            } else {
                $this->addIssue($oSectionIssues, $oObject, 'Does not exist in storage', $aObjectsWithIssues);
            }

            $oProgress->advance();
        }
        $oProgress->finish();

        if (!empty($aObjectsWithIssues)) {
            $oSectionIssues->writeln('');
            $this->warning([
                count($aObjectsWithIssues) . ' issues were detected and are detailed above',
            ]);
        }

        return static::EXIT_CODE_SUCCESS;
    }

    private function addIssue(ConsoleSectionOutput $oSection, \Nails\Cdn\Resource\CdnObject $oObject, string $sIssue, array &$aObjectsWithIssues): void
    {
        if (empty($aObjectsWithIssues)) {
            $oSection->clear(1);
        }
        $aObjectsWithIssues[] = $oObject;
        $oSection->writeln(sprintf('- Object #%s (<info>%s</info>): %s', $oObject->id, $oObject->driver, $sIssue));
    }
}

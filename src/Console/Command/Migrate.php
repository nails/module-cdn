<?php

namespace Nails\Cdn\Console\Command;

use Nails\Console\Command\Base;
use Nails\Factory;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Migrate extends Base
{
    /**
     * Stores driver instances
     * @var array
     */
    protected $aDriverInstances = [];

    // --------------------------------------------------------------------------

    /**
     * Configures the app
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('cdn:migrate')
            ->setDescription('Migrates CDN between drivers')
            ->addArgument(
                'driver',
                InputArgument::OPTIONAL,
                'Specify which driver to migrate to; will auto-detect if not specified'
            )
            ->addOption(
                'overwrite',
                'o',
                InputOption::VALUE_NONE,
                'Overwrite target objects if they exist'
            )
            ->addOption(
                'remove-src',
                'r',
                InputOption::VALUE_NONE,
                'Remove the original file'
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Executes the app
     *
     * @param  InputInterface  $oInput  The Input Interface provided by Symfony
     * @param  OutputInterface $oOutput The Output Interface provided by Symfony
     *
     * @throws \Exception
     * @return void
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput)
    {
        parent::execute($oInput, $oOutput);

        $oOutput->writeln('');
        $oOutput->writeln('<info>--------------------</info>');
        $oOutput->writeln('<info>CDN Driver Migration</info>');
        $oOutput->writeln('<info>--------------------</info>');
        $oOutput->writeln('Beginning...');

        $oStorageDriver = Factory::model('StorageDriver', 'nailsapp/module-cdn');
        $sDriver        = $oInput->getArgument('driver');
        $bOverwrite     = $oInput->getOption('overwrite');
        $bRemove        = $oInput->getOption('remove-src');
        $aAllDrivers    = $oStorageDriver->getAll();

        //  Auto-detect the driver if not specified
        if (empty($sDriver)) {
            $oEnabledDriver = $oStorageDriver->getEnabled();
            if (empty($oEnabledDriver)) {
                throw new \Exception('No CDN drivers are enabled');
            } else {
                $sDriver = $oEnabledDriver->slug;
            }
        }

        //  Validate driver
        $aDrivers    = [];
        $aDriversOld = [];
        foreach ($aAllDrivers as $oDriver) {
            $aDrivers[$oDriver->slug] = $oDriver;
            if ($oDriver->slug != $sDriver) {
                $aDriversOld[$oDriver->slug] = $oDriver;
            }
        }

        if (!array_key_exists($sDriver, $aDrivers)) {
            throw new \Exception('"' . $sDriver . '" is not a valid storage driver.');
        }

        // --------------------------------------------------------------------------

        //  Work out what's going to happen
        $oObjectModel = Factory::model('Object', 'nailsapp/module-cdn');

        //  How many objects are already migrated
        $iMigrated = $oObjectModel->countAll([
            'where' => [
                ['driver', $sDriver],
            ],
        ]);

        //  How many objects will be migrated
        $iToMigrate = $oObjectModel->countAll([
            'where_in' => [
                ['driver', array_keys($aDriversOld)],
            ],
        ]);

        //  How many objects which need to be migrated but won't be
        $iCannotMigrate = $oObjectModel->countAll([
            'where_not_in' => [
                ['driver', array_keys($aDrivers)],
            ],
        ]);

        //  Summarise for the user and seek confirmation
        $oOutput->writeln('');
        $oOutput->writeln('Migrate CDN objects to <info>' . $sDriver . '</info>');
        $oOutput->writeln('');
        if ($iToMigrate) {
            $oOutput->writeln('- <info>' . $iToMigrate . '</info> objects will be migrated');
            $oOutput->writeln('- <info>' . $iMigrated . '</info> objects already exist and will not be migrated');
            if (!empty($iCannotMigrate)) {
                $oOutput->writeln(
                    '- <info>' . $iCannotMigrate . '</info> objects cannot be migrated as their driver is not available'
                );
            }

            if ($bOverwrite) {
                $oOutput->writeln('- <fg=red;options=bold>Target overwriting is enabled</>');
            }

            if ($bRemove) {
                $oOutput->writeln('- <fg=red;options=bold>The original file will be deleted</>');
            }

            $oOutput->writeln('');
            if ($this->confirm('Continue?', true)) {
                $oOutput->writeln('<comment>Migrating...</comment>');
                $this->doMigrate($sDriver, $bOverwrite, $bRemove, $aDriversOld);
            } else {
                $oOutput->writeln('');
                $oOutput->writeln('Aborting Migration');
            }

        } else {
            $oOutput->writeln('There are no objects requiring migration.');
        }

        // --------------------------------------------------------------------------

        //  Cleaning up
        $oOutput->writeln('');
        $oOutput->writeln('<comment>Cleaning up...</comment>');

        //  And we're done!
        $oOutput->writeln('');
        $oOutput->writeln('Complete!');
    }

    // --------------------------------------------------------------------------

    protected function doMigrate($sDriver, $bOverwrite, $bRemove, $aDriversOld)
    {
        $oOutput        = $this->oOutput;
        $oObjectModel   = Factory::model('Object', 'nailsapp/module-cdn');
        $oStorageDriver = Factory::model('StorageDriver', 'nailsapp/module-cdn');

        $oProgress = new ProgressBar($oOutput, 100);
        $oProgress->setFormat("\n%current%% [%bar%]\n\nElapsed:   %elapsed:6s%\nEstimated: %estimated:-6s%");
        $oProgress->setProgressCharacter('');
        $oProgress->setBarCharacter('❚');
        $oProgress->setEmptyBarCharacter(' ');
        $oProgress->start();

        //  Set up a log file to catch errors
        $oNow = Factory::factory('DateTime');
        $sLog = 'application/logs/cdn-migrate-' . $oNow->format('Y-m-d_H-i-s') . '.php';
        $rLog = fopen($sLog, 'w');
        fwrite($rLog, '<?php die("No direct access allowed"); ?>' . "\n");

        $iMigrated = 0;
        $iFailures = 0;
        $aFailures = [];
        $iProgress = 0;

        while ($iProgress < 100) {

            //  Get total number of objects still to migrate
            $aData      = [
                'where_in'     => [
                    ['driver', array_keys($aDriversOld)],
                ],
                'where_not_in' => [
                    ['id', $aFailures],
                ],
            ];
            $iToMigrate = $oObjectModel->countAll($aData);

            if ($iToMigrate) {

                try {

                    $oInstanceNew = $oStorageDriver->getInstance($sDriver);

                    //  Migrate the next item and record any failures
                    //  Get the next item
                    $aObjects = $oObjectModel->getAll(0, 1, $aData + ['expand' => ['bucket']]);
                    if (empty($aObjects)) {
                        throw new \Exception('FAILURE: No objects returned');
                    }

                    $oObject      = $aObjects[0];
                    $oInstanceOld = $oStorageDriver->getInstance($oObject->driver);

                    //  Make sure the object doesn't exist already
                    if ($bOverwrite || !$oInstanceNew->objectExists($oObject->file->name->disk, $oObject->bucket->slug)) {

                        //  Attempt migration
                        $sLocalPath = $oInstanceOld->objectLocalPath($oObject->bucket->slug, $oObject->file->name->disk);

                        if (empty($sLocalPath)) {
                            throw new \Exception('Failed to retrieve local path');
                        } elseif (!file_exists($sLocalPath)) {
                            throw new \Exception('File does not exist locally: ' . $sLocalPath);
                        }

                        $bResult = $oInstanceNew->objectCreate((object) [
                            'bucket'   => (object) [
                                'slug' => $oObject->bucket->slug,
                            ],
                            'filename' => $oObject->file->name->disk,
                            'file'     => $sLocalPath,
                            'mime'     => $oObject->file->mime,
                        ]);

                        if (!$bResult) {
                            throw new \Exception(
                                'Failed to upload to new storage driver. ' . $oInstanceNew->lastError()
                            );
                        }
                    }

                    $oObjectModel->update($oObject->id, ['driver' => $sDriver]);
                    fwrite($rLog, 'Object #' . $oObject->id . ': Migrated' . "\n");
                    $iMigrated++;

                    //  Remove source file
                    if ($bRemove) {
                        $oInstanceOld->objectDestroy(
                            $oObject->file->name->disk,
                            $oObject->bucket->slug
                        );
                        fwrite($rLog, 'Object #' . $oObject->id . ': Source Removed' . "\n");
                    }

                } catch (\Exception $e) {
                    $iFailures++;
                    if (!empty($oObject)) {
                        $aFailures[] = $oObject->id;
                        fwrite($rLog, 'Object #' . $oObject->id . ': ' . $e->getMessage() . "\n");
                    } else {
                        fwrite($rLog, $e->getMessage() . "\n");
                    }
                }

                // Set progress
                $iTotalMigrated = $iMigrated + $iFailures;
                $iProgress      = floor($iTotalMigrated / ($iToMigrate + $iTotalMigrated) * 100);
                $oProgress->setProgress($iProgress);

            } else {
                $oProgress->finish();
                $iProgress = 100;
            }
        }

        fclose($rLog);

        $oOutput->writeln('');
        $oOutput->writeln('<comment>Migration complete</comment>');
        $oOutput->writeln('');
        $oOutput->writeln('- <info>' . $iMigrated . '</info> objects were successfully migrated');

        if ($iFailures) {
            $oOutput->writeln(
                '- <fg=red>' . $iFailures . '</> objects failed to migrate; log file located at <comment>' . $sLog . '</comment>'
            );
        }
    }
}

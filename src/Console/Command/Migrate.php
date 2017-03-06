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
            ->setDescription('[WIP] Migrates CDN between drivers')
            ->addArgument(
                'driver',
                InputArgument::OPTIONAL,
                'Specify which driver to migrate to; will auto-detect if not specified'
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

        $oDb         = Factory::service('Database', 'nailsapp/module-console');
        $oAppSetting = Factory::model('AppSetting');

        Factory::helper('string');

        $sDriver = $oInput->getArgument('driver');
        $bRemove = $aDriversRaw = $oInput->getOption('remove-src');

        if (empty($sDriver)) {
            $oQuery = $oDb->query('
                SELECT
                    value
                FROM `' . $oAppSetting->getTableName() . '`
                WHERE
                `grouping` = "nailsapp/module-cdn"
                AND `key` = "enabled_driver_storage"
            ');
            if ($oQuery->rowCount()) {
                $sDriver = json_decode($oQuery->fetchObject()->value);
            }
        }

        //  Validate driver
        $aDriversRaw = _NAILS_GET_DRIVERS('nailsapp/module-cdn', 'storage');
        $aDrivers    = [];
        $aDriversOld = [];
        foreach ($aDriversRaw as $oDriver) {
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
        //  How many objects are already migrated
        $iMigrated = $oDb->query('
            SELECT
                COUNT(*) total
            FROM `' . NAILS_DB_PREFIX . 'cdn_object`
            WHERE
            `driver` = "' . $sDriver . '"
        ')->fetchObject()->total;

        //  How many objects will be migrated
        $iToMigrate = $oDb->query('
            SELECT
                COUNT(*) total
            FROM `' . NAILS_DB_PREFIX . 'cdn_object`
            WHERE
            `driver` IN ("' . implode('","', array_keys($aDriversOld)) . '")
        ')->fetchObject()->total;

        //  How many objects which need to be migrated but won't be
        $iCannotMigrate = $oDb->query('
            SELECT
                COUNT(*) total
            FROM `' . NAILS_DB_PREFIX . 'cdn_object`
            WHERE
            `driver` NOT IN ("' . implode('","', array_keys($aDrivers)) . '")
        ')->fetchObject()->total;

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

            if ($bRemove) {
                $oOutput->writeln('- <fg=red;options=bold>The original file will be deleted</>');
            }

            $oOutput->writeln('');
            if ($this->confirm('Continue?', true)) {
                $oOutput->writeln('<comment>Migrating...</comment>');
                $this->doMigrate($sDriver, $bRemove, $aDrivers, $aDriversOld);
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

    protected function doMigrate($sDriver, $bRemove, $aDrivers, $aDriversOld)
    {
        $oOutput   = $this->oOutput;
        $oDb       = Factory::service('Database', 'nailsapp/module-console');
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

            $sFailIdSql = !empty($aFailures) ? 'AND `o`.`id` NOT IN (' . implode(',', $aFailures) . ')' : '';

            //  Get total number of objects still to migrate
            $sSql = 'SELECT
                        COUNT(*) total
                    FROM `' . NAILS_DB_PREFIX . 'cdn_object` o
                    WHERE
                   `o`.`driver` IN ("' . implode('","', array_keys($aDriversOld)) . '")
                   ' . $sFailIdSql;

            $iToMigrate = $oDb->query($sSql)->fetchObject()->total;

            if ($iToMigrate) {

                $oInstanceNew = $this->driverInstance($aDrivers[$sDriver]);

                try {

                    //  Migrate the next item and record any failures
                    //  Get the next item
                    $oQuery = $oDb->query('
                        SELECT
                            o.id,
                            o.filename,
                            o.driver,
                            b.slug bucket
                        FROM `' . NAILS_DB_PREFIX . 'cdn_object` o
                        LEFT JOIN `' . NAILS_DB_PREFIX . 'cdn_bucket` b ON b.id = o.bucket_id
                        WHERE
                        `driver` IN ("' . implode('","', array_keys($aDriversOld)) . '")
                        ' . $sFailIdSql . '
                        LIMIT 1
                    ');

                    if (!$oQuery->rowCount()) {
                        throw new \Exception('FAILURE: No objects returned');
                    }

                    $oObject = $oQuery->fetchObject();

                    //  Attempt migration
                    $oInstanceOld = $this->driverInstance($aDrivers[$oObject->driver]);
                    $sLocalPath   = $oInstanceOld->objectLocalPath($oObject->bucket, $oObject->filename);
                    if (!file_exists($sLocalPath)) {
                        throw new \Exception('File does not exist: ' . $sLocalPath);
                    }

                    //  @todo - use new driver to upload to target

                    //  If successful, update the record
                    $oDb->query('
                        UPDATE `' . NAILS_DB_PREFIX . 'cdn_object`
                        SET 
                        `driver` = "' . $sDriver . '"
                        WHERE `id` = ' . $oObject->id . '
                    ');

                    fwrite($rLog, 'Object #' . $oObject->id . ': Migrated' . "\n");
                    $iMigrated++;

                    //  Remove source file
                    if ($bRemove) {
                        //  @todo - remove source file
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

    // --------------------------------------------------------------------------

    /**
     * Load an instance of the driver
     *
     * @param $oDriver
     *
     * @return mixed
     * @throws \Exception
     */
    protected function driverInstance($oDriver)
    {
        if (!array_key_exists($oDriver->slug, $this->aDriverInstances)) {
            $this->aDriverInstances[$oDriver->slug] = _NAILS_GET_DRIVER_INSTANCE($oDriver);
            if (empty($this->aDriverInstances[$oDriver->slug])) {
                throw new \Exception('Failed to load driver instance: ' . $oDriver->slug);
            }

            //  @todo - configure the driver
        }

        return $this->aDriverInstances[$oDriver->slug];
    }
}

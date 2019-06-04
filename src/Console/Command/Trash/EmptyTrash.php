<?php

/**
 * The cdn:trash:empty console command
 *
 * @package  Nails
 * @category Console
 */

namespace Nails\Cdn\Console\Command\Trash;

use Nails\Cdn\Model\CdnObject\Trash;
use Nails\Cdn\Resource\CdnObject;
use Nails\Cdn\Service\Cdn;
use Nails\Console\Command\Base;
use Nails\Factory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EmptyTrash
 *
 * @package Nails\Cdn\Console\Command\Trash
 */
class EmptyTrash extends Base
{
    /**
     * Configure the cdn:trash:empty command
     */
    protected function configure()
    {
        $this
            ->setName('cdn:trash:empty')
            ->setDescription('Deletes items which have been in the trash for ' . Factory::property('trashRetention', 'nails/module-cdn') . ' days');
    }

    // --------------------------------------------------------------------------

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

        $this->banner('CDN: Trash: Empty');
        $oOutput->writeln('Deleting trashed items older than <comment>' . Factory::property('trashRetention', 'nails/module-cdn') . '</comment> days');
        $oOutput->writeln('');

        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', 'nails/module-cdn');

        /** @var Trash $oModel */
        $oModel = Factory::model('ObjectTrash', 'nails/module-cdn');

        /** @var \DateTime $oNow */
        $oNow = Factory::factory('DateTime');
        $oNow->sub(new \DateInterval('P' . Factory::property('trashRetention', 'nails/module-cdn') . 'D'));

        $aTrashedItems = $oModel->getAll([
            'where' => [
                ['trashed <', $oNow->format('Y-m-d H:i:s')],
            ],
        ]);

        $oOutput->writeln('Deleting <comment>' . count($aTrashedItems) . '</comment> items...');

        /** @var CdnObject $oObject */
        foreach ($aTrashedItems as $oObject) {

            $oOutput->write(' ↳ Deleting Object <comment>' . $oObject->id . '</comment> (' . $oObject->file->name->human . ')... ');
            if ($oCdn->objectDestroy($oObject->id)) {
                $oOutput->writeln('<comment>done</comment>');
            } else {
                $oOutput->writeln('<error>Error:' . $oCdn->lastError() . '</error>');
            }
        }

        $oOutput->writeln('');
        $oOutput->writeln('Complete');
        $oOutput->writeln('');

        return static::EXIT_CODE_SUCCESS;
    }
}

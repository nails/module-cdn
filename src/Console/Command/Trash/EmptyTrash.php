<?php

/**
 * The cdn:trash:empty console command
 *
 * @package  Nails
 * @category Console
 */

namespace Nails\Cdn\Console\Command\Trash;

use Nails\Cdn\Constants;
use Nails\Cdn\Model\CdnObject\Trash;
use Nails\Cdn\Resource\CdnObject;
use Nails\Cdn\Service\Cdn;
use Nails\Config;
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
     * The number of days to keep trashed objects
     *
     * @var int
     */
    protected $iTrashRetention;

    // --------------------------------------------------------------------------

    /**
     * EmptyTrash constructor.
     *
     * @param string|null $name
     */
    public function __construct(string $name = null)
    {
        $this->iTrashRetention = (int) Config::get('CDN_TRASH_RETENTION', 180);
        parent::__construct($name);
    }

    // --------------------------------------------------------------------------

    /**
     * Configure the cdn:trash:empty command
     */
    protected function configure()
    {
        $this
            ->setName('cdn:trash:empty')
            ->setDescription('Deletes items which have been in the trash for ' . $this->iTrashRetention . ' days');
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
        $oOutput->writeln('Deleting trashed items older than <comment>' . $this->iTrashRetention . '</comment> days');
        $oOutput->writeln('');

        /** @var Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Constants::MODULE_SLUG);

        /** @var Trash $oModel */
        $oModel = Factory::model('ObjectTrash', Constants::MODULE_SLUG);

        /** @var \DateTime $oNow */
        $oNow = Factory::factory('DateTime');
        $oNow->sub(new \DateInterval('P' . $this->iTrashRetention . 'D'));

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

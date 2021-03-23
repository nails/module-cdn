<?php

namespace Nails\Cdn\Console\Command;

use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Cdn\Model;
use Nails\Cdn\Resource;
use Nails\Cdn\Service\Cdn;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Helper\Model\Expand;
use Nails\Console\Command\Base;
use Nails\Factory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Import
 *
 * @package Nails\Cdn\Console\Command
 */
class Import extends Base
{
    /** @var Model\CdnObject\Import */
    private $oModel;

    /** @var Cdn */
    private $oCdn;

    // --------------------------------------------------------------------------

    /**
     * Import constructor.
     *
     * @throws FactoryException
     */
    public function __construct()
    {
        parent::__construct();
        $this->oModel = Factory::model('ObjectImport', Constants::MODULE_SLUG);
        $this->oCdn   = Factory::service('Cdn', Constants::MODULE_SLUG);
    }

    // --------------------------------------------------------------------------

    /**
     * Configures the command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('cdn:import')
            ->setDescription('Imports any pending items from the import queue');
    }

    // --------------------------------------------------------------------------

    /**
     * Executes the app
     *
     * @param InputInterface  $oInput  The Input Interface provided by Symfony
     * @param OutputInterface $oOutput The Output Interface provided by Symfony
     *
     * @return int
     * @throws CdnException
     * @throws FactoryException
     * @throws ModelException
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput)
    {
        parent::execute($oInput, $oOutput);

        $this->banner('CDN: Import');

        // --------------------------------------------------------------------------

        while ($oItem = $this->fetchPendingItem()) {

            $oOutput->writeln('Picked up item #' . $oItem->id);
            $oOutput->writeln(' ↳ URL:  <comment>' . $oItem->url . '</comment>');
            $oOutput->writeln(' ↳ mime: <comment>' . $oItem->mime . '</comment>');
            $oOutput->writeln(' ↳ size: <comment>' . formatBytes($oItem->size) . '</comment>');
            $oOutput->write('Beginning import...');

            try {

                if (!$this->oCdn->objectCreate($oItem->url, $oItem->bucket->slug)) {
                    throw new CdnException(sprintf(
                        'Failed to import. %s',
                        $this->oCdn->lastError()
                    ));
                }

                $this->setStatus($oItem, $this->oModel::STATUS_COMPLETE);
                $oOutput->writeln('<info>done!</info>');

            } catch (\Exception $e) {
                $oOutput->writeln('<error>ERROR: ' . $e->getMessage() . '</error>');
                $this->setStatus($oItem, $this->oModel::STATUS_ERROR, $e->getMessage());
            }
        }

        // --------------------------------------------------------------------------

        //  Cleaning up
        $oOutput->writeln('');
        $oOutput->writeln('<comment>Cleaning up...</comment>');

        //  And we're done!
        $oOutput->writeln('');
        $oOutput->writeln('Complete!');

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches the next pending item
     *
     * @return Resource\CdnObject\Import|null
     * @throws CdnException
     * @throws FactoryException
     * @throws ModelException
     */
    protected function fetchPendingItem(): ?Resource\CdnObject\Import
    {
        $aItems = $this->oModel
            ->skipCache()
            ->getAll([
                new Expand('bucket'),
                'where' => [
                    ['status', $this->oModel::STATUS_PENDING],
                ],
                'sort'  => [
                    ['created', $this->oModel::SORT_ASC],
                ],
                'limit' => 1,
            ]);

        $oItem = current($aItems) ?: null;

        if (!empty($oItem)) {
            $this->setStatus($oItem, $this->oModel::STATUS_IN_PROGRESS);
        }

        return $oItem;
    }

    // --------------------------------------------------------------------------

    /**
     * @param Resource\CdnObject\Import $oItem
     * @param string                    $sStatus
     * @param string|null               $sError
     *
     * @throws CdnException
     * @throws FactoryException
     * @throws ModelException
     */
    private function setStatus(Resource\CdnObject\Import $oItem, string $sStatus, string $sError = null): void
    {
        if (!$this->oModel->update($oItem->id, ['status' => $sStatus, 'error' => $sError])) {
            throw new CdnException(
                'Failed to mark item as `' . $sStatus . '`. ' . $this->oModel->lastError()
            );
        }
    }
}

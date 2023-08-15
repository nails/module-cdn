<?php

namespace Nails\Cdn\Console\Command\Monitor;

use Nails\Cdn\Constants;
use Nails\Cdn\Factory\Monitor\Detail;
use Nails\Cdn\Model;
use Nails\Cdn\Resource;
use Nails\Common\Helper\Model\Expand;
use Nails\Console\Command\Base;
use Nails\Console\Exception\ConsoleException;
use Nails\Factory;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Usages
 *
 * @package Nails\Cdn\Console\Command
 */
class Usages extends Base
{
    /**
     * Configures the command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('cdn:monitor:usages')
            ->setDescription('Determines where a given object is being used')
            ->addArgument('object_id', InputArgument::REQUIRED);
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

        $iObjectId = (int) $oInput->getArgument('object_id');

        $oObject = $this->verifyObject($iObjectId);

        /** @var \Nails\Cdn\Service\Monitor $oService */
        $oService = Factory::service('Monitor', Constants::MODULE_SLUG);

        $oSection           = $oOutput->section();
        $oProgressIndicator = new ProgressIndicator($oSection);
        $oProgressIndicator->start('Locating usages...');

        $aResults = $oService->locate($oObject, $oProgressIndicator);

        $oSection->clear(1);

        if (empty($aResults)) {

            $oOutput->writeln('<warning>No usages found for this object</warning>');
            $oOutput->writeln('');
            return static::EXIT_CODE_SUCCESS;

        } else {
            $oTable = new Table($oOutput);
            $oTable
                ->setHeaderTitle(sprintf('Usages (%s)', number_format(count($aResults))))
                ->setHeaders([
                    'Monitor',
                    'Detail',
                ])
                ->setRows(
                    array_map(
                        function (Detail $oDetail) {
                            return [
                                $oDetail->getMonitor()->getLabel(),
                                json_encode($oDetail->getData()),
                            ];
                        },
                        $aResults
                    )
                )
                ->render();
        }

        $oOutput->writeln('');
        $sAnswer = $this->choose('What would you like to do next?', [
            'd' => 'Delete this object',
            'r' => 'Replace this object',
            'x' => 'Exit',
        ], 'x');

        switch ($sAnswer) {
            case 'd':
                return $this->delete($aResults);

            case 'r':
                return $this->replace(
                    $aResults,
                    (int) $this->ask('Replace with (Object ID)', null)
                );

            case 'q':
            default:
                return static::EXIT_CODE_SUCCESS;
        }
    }

    // --------------------------------------------------------------------------

    private function delete(array $aResults): int
    {
        $this
            ->trackProgress(
                'Deleting usages...',
                function (Detail $oDetail) {
                    $oDetail->delete();
                },
                $aResults
            );

        $this->warning([
            'Note: This operation has only affected references to this object,',
            'the actual object has not been deleted.',
        ]);
        $this->oOutput->writeln('');

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    private function replace(array $aResults, int $iReplacementId): int
    {
        $oReplacement = $this->verifyObject($iReplacementId);
        if ($this->confirm('Continue?', true)) {
            $this
                ->trackProgress(
                    'Replacing usages...',
                    function (Detail $oDetail) use ($oReplacement) {
                        $oDetail->replace($oReplacement);
                    },
                    $aResults
                );
        }

        return static::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    private function verifyObject(int $iObjectId): Resource\CdnObject
    {
        /** @var Model\CdnObject $oObjectModel */
        $oModel = Factory::model('Object', Constants::MODULE_SLUG);
        /** @var Resource\CdnObject|null $oObject */
        $oObject = $oModel->getById($iObjectId, [
            new Expand('bucket'),
        ]);

        if (empty($oObject)) {
            throw new ConsoleException('Invalid object ID');
        }

        $this->keyValueList([
            'ID'                 => $oObject->id,
            'Filename'           => $oObject->file->name->human,
            'Filename (on disk)' => $oObject->file->name->disk,
            'MIME'               => $oObject->file->mime,
            'Size'               => $oObject->file->size->human,
            'Bucket'             => sprintf(
                '%s (<info>%s</info>)',
                $oObject->bucket->label,
                $oObject->bucket->slug
            ),
            'Driver'             => $oObject->driver,
        ]);

        return $oObject;
    }

    // --------------------------------------------------------------------------

    private function trackProgress(string $sTitle, \Closure $oClosure, array $aItems): void
    {
        $this->oOutput->writeln('');
        $this->oOutput->writeln($sTitle);
        $oProgressBar = new ProgressBar($this->oOutput, count($aItems));
        $oProgressBar->start();

        $iStart = microtime(true);

        foreach ($aItems as $oItem) {
            $oClosure($oItem);
            $oProgressBar->advance();
        }

        $iEnd = microtime(true);

        $oProgressBar->finish();
        $this->oOutput->writeln('');
        $this->oOutput->writeln(sprintf(
            '<comment>Complete!</comment> Job took %s seconds',
            number_format($iEnd - $iStart, 2)
        ));
        $this->oOutput->writeln('');
    }
}

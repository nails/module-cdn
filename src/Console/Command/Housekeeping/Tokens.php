<?php

/**
 * The cdn:housekeeping:tokens console command
 *
 * @package  Nails
 * @category Console
 */

namespace Nails\Cdn\Console\Command\Housekeeping;

use DateTime;
use Nails\Cdn\Constants;
use Nails\Cdn\Model\Token;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Service\Database;
use Nails\Console\Command\Base;
use Nails\Factory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Tokens
 *
 * @package Nails\Cdn\Console\Command\Housekeeping
 */
class Tokens extends Base
{
    /**
     * Configure the cdn:trash:empty command
     */
    protected function configure()
    {
        $this
            ->setName('cdn:housekeeping:tokens')
            ->setDescription('Deletes expired tokens');
    }

    // --------------------------------------------------------------------------

    /**
     * Execute the command
     *
     * @param InputInterface  $oInput  The Input Interface provided by Symfony
     * @param OutputInterface $oOutput The Output Interface provided by Symfony
     *
     * @return int
     * @throws FactoryException
     * @throws ModelException
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput)
    {
        parent::execute($oInput, $oOutput);

        $this->banner('CDN: Housekeeping: Tokens');
        $oOutput->writeln('Deleting expired tokens');
        $oOutput->writeln('');

        /** @var Database $db */
        $db = Factory::service('Database');
        /** @var Token $tokenModel */
        $tokenModel = Factory::model('Token', Constants::MODULE_SLUG);
        /** @var DateTime $now */
        $now = Factory::factory('DateTime');

        $result = $db
            ->query(
                sprintf(
                    'DELETE FROM `%s` WHERE `expires` < ?`',
                    $tokenModel->getTableName()
                ),
                [
                    $now->format('Y-m-d H:i:s'),
                ]
            );

        $oOutput->writeln('Deleted <comment>' . number_format($result->num_rows()) . '</comment> expired tokens');

        $oOutput->writeln('');
        $oOutput->writeln('Complete');
        $oOutput->writeln('');

        return static::EXIT_CODE_SUCCESS;
    }
}

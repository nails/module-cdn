<?php

namespace Nails\Cdn\Settings;

use Nails\Cdn\Service\StorageDriver;
use Nails\Common\Helper\Form;
use Nails\Common\Interfaces;
use Nails\Common\Service\FormValidation;
use Nails\Components\Setting;
use Nails\Cdn\Constants;
use Nails\Factory;

/**
 * Class General
 *
 * @package Nails\Cdn\Settings
 */
class General implements Interfaces\Component\Settings
{
    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return 'CDN';
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getPermissions(): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        /** @var StorageDriver $oDriverService */
        $oDriverService = Factory::service('StorageDriver', Constants::MODULE_SLUG);

        /** @var Setting $oDriver */
        $oDriver = Factory::factory('ComponentSetting');
        $oDriver
            ->setKey($oDriverService->getSettingKey())
            ->setType($oDriverService->isMultiple()
                ? Form::FIELD_DROPDOWN_MULTIPLE
                : Form::FIELD_DROPDOWN
            )
            ->setLabel('Driver')
            ->setFieldset('Driver')
            ->setClass('select2')
            ->setOptions(['' => 'No Driver Selected'] + $oDriverService->getAllFlat())
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        return [
            $oDriver,
        ];
    }
}

<?php

namespace Nails\Cdn;

/**
 * Class Constants
 *
 * @package Nails\Cdn
 */
class Constants
{
    /**
     * The slug for this module
     *
     * @var string
     */
    const MODULE_SLUG = 'nails/module-cdn';

    const MEDIA_MANAGER_V1     = 1;
    const MEDIA_MANAGER_V1_URL = 'admin/cdn/mediaManagerV1';

    const MEDIA_MANAGER_V2     = 2;
    const MEDIA_MANAGER_V2_URL = 'admin/cdn/mediaManagerV2';

    /**
     * Validation rules provided by this module (see src/Validation/Rule)
     */
    const RULE_OBJECT_PICKER_MULTI_ALL_REQUIRED    = 'cdnObjectPickerMultiAllRequired';
    const RULE_OBJECT_PICKER_MULTI_LABEL_REQUIRED  = 'cdnObjectPickerMultiLabelRequired';
    const RULE_OBJECT_PICKER_MULTI_OBJECT_REQUIRED = 'cdnObjectPickerMultiObjectRequired';
}

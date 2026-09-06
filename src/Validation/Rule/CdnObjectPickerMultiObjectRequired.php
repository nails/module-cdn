<?php

namespace Nails\Cdn\Validation\Rule;

use Nails\Common\Validation\AbstractRule;
use Nails\Common\Validation\Context;

/**
 * Rule: `cdnObjectPickerMultiObjectRequired` — every item in a CDN Object Multi Picker must have an object set
 */
class CdnObjectPickerMultiObjectRequired extends AbstractRule
{
    public const NAME            = 'cdnObjectPickerMultiObjectRequired';
    public const DEFAULT_MESSAGE = 'All items must have a file set.';

    public function acceptsArrays(): bool
    {
        return true;
    }

    public function apply(mixed $mValue, Context $oContext): bool
    {
        if (!is_array($mValue)) {
            return false;
        }

        foreach ($mValue as $aValue) {
            if (!is_array($aValue) || empty($aValue['object_id'])) {
                return false;
            }
        }

        return true;
    }
}

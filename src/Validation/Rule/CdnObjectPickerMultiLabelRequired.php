<?php

namespace Nails\Cdn\Validation\Rule;

use Nails\Common\Validation\AbstractRule;
use Nails\Common\Validation\Context;

/**
 * Rule: `cdnObjectPickerMultiLabelRequired` — every item in a CDN Object Multi Picker must have a label set
 */
class CdnObjectPickerMultiLabelRequired extends AbstractRule
{
    public const NAME            = 'cdnObjectPickerMultiLabelRequired';
    public const DEFAULT_MESSAGE = 'All items must have a label set.';

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
            if (!is_array($aValue) || empty($aValue['label'])) {
                return false;
            }
        }

        return true;
    }
}

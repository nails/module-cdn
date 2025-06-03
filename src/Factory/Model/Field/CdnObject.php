<?php

namespace Nails\Cdn\Factory\Model\Field;

use Nails\Cdn\Helper\Form;
use Nails\Common\Factory\Model\Field;

class CdnObject extends Field
{
    /** @var string */
    public $type = Form::FIELD_OBJECT_PICKER;

    public ?string $bucket = null;

    // --------------------------------------------------------------------------

    /**
     * Sets the bucket
     *
     * @param string $sBucket
     *
     * @return $this
     */
    public function setBucket(string $sBucket): self
    {
        $this->bucket = $sBucket;
        return $this;
    }
}

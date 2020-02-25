<?php

namespace Nails\Cdn\Resource\UrlGenerator;

use Nails\Cdn\Service;
use Nails\Cdn\Resource\UrlGenerator;

class Crop extends UrlGenerator
{
    protected $iWidth;
    protected $iHeight;

    // --------------------------------------------------------------------------

    public function setWidth(int $iWidth): self
    {
        $this->iWidth = $iWidth;
        return $this;
    }

    // --------------------------------------------------------------------------

    public function setHeight(int $iHeight): self
    {
        $this->iHeight = $iHeight;
        return $this;
    }
}

<?php

namespace Nails\Cdn\Factory\Monitor;

use Nails\Cdn\Interfaces\Monitor;
use Nails\Cdn\Resource\CdnObject;

class Detail
{
    protected Monitor $oMonitor;

    /**
     * Whatever data is useful to the monitor for manipulating the object
     */
    protected ?object $oData = null;

    // --------------------------------------------------------------------------

    public function __construct(Monitor $oMonitor, object $oData = null)
    {
        $this->oMonitor = $oMonitor;
        if ($oData) {
            $this->oData($oData);
        }
    }

    // --------------------------------------------------------------------------

    public function getMonitor(): Monitor
    {
        return $this->oMonitor;
    }

    // --------------------------------------------------------------------------

    /**
     * @param object $oData
     */
    public function setData(object $oData): self
    {
        $this->oData = $oData;
        return $this;
    }

    // --------------------------------------------------------------------------

    public function getData(): ?object
    {
        return $this->oData;
    }

    // --------------------------------------------------------------------------

    public function delete(CdnObject $oObject): void
    {
        $this->oMonitor->delete($this, $oObject);
    }

    // --------------------------------------------------------------------------

    public function replace(CdnObject $oObject, CdnObject $oReplacement): void
    {
        $this->oMonitor->replace($this, $oObject, $oReplacement);
    }
}

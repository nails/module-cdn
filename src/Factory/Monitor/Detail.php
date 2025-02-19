<?php

namespace Nails\Cdn\Factory\Monitor;

use Nails\Cdn\Factory\Monitor\Detail\Action;
use Nails\Cdn\Interfaces\Monitor;
use Nails\Cdn\Resource\CdnObject;

class Detail
{
    protected Monitor $oMonitor;

    /**
     * Whatever data is useful to the monitor for manipulating the object
     */
    protected ?object $oData = null;

    /**
     * An array of actions, rendered as links in the UI
     */
    protected array $aActions = [];

    // --------------------------------------------------------------------------

    public function __construct(Monitor $oMonitor, object $oData = null, array $aActions = [])
    {
        $this->oMonitor = $oMonitor;

        $this->setData($oData);
        $this->setActions($aActions);
    }

    // --------------------------------------------------------------------------

    public function getMonitor(): Monitor
    {
        return $this->oMonitor;
    }

    // --------------------------------------------------------------------------

    public function setActions(array $aActions): self
    {
        $this->aActions = $aActions;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * @return Action[]
     */
    public function getActions(): array
    {
        return $this->aActions;
    }

    // --------------------------------------------------------------------------

    public function setData(?object $oData): self
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

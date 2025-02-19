<?php

namespace Nails\Cdn\Factory\Monitor\Detail;

class Action
{
    public function __construct(
        protected string $sUrl = '',
        protected string $sLabel = '',
        protected string $sClass = 'btn-default',
        protected string $sTarget = '',
        protected bool $bConfirm = false,
        protected string $sConfirmTitle = '',
        protected string $sConfirmBody = ''
    ) {
    }

    // --------------------------------------------------------------------------

    public function getUrl(): string
    {
        return $this->sUrl;
    }

    // --------------------------------------------------------------------------

    public function setUrl(string $sUrl): self
    {
        $this->sUrl = $sUrl;
        return $this;
    }

    // --------------------------------------------------------------------------

    public function getLabel(): string
    {
        return $this->sLabel;
    }

    // --------------------------------------------------------------------------

    public function setLabel(string $sLabel): self
    {
        $this->sLabel = $sLabel;
        return $this;
    }

    // --------------------------------------------------------------------------

    public function getClass(): string
    {
        return $this->sClass;
    }

    // --------------------------------------------------------------------------

    public function setClass(string $sClass): self
    {
        $this->sClass = $sClass;
        return $this;
    }

    // --------------------------------------------------------------------------

    public function getTarget(): string
    {
        return $this->sTarget;
    }

    // --------------------------------------------------------------------------

    public function setTarget(string $sTarget): self
    {
        $this->sTarget = $sTarget;
        return $this;
    }

    // --------------------------------------------------------------------------

    public function isConfirm(): bool
    {
        return $this->bConfirm;
    }

    // --------------------------------------------------------------------------

    public function setConfirm(bool $bConfirm): self
    {
        $this->bConfirm = $bConfirm;
        return $this;
    }

    // --------------------------------------------------------------------------

    public function getConfirmTitle(): string
    {
        return $this->sConfirmTitle;
    }

    // --------------------------------------------------------------------------

    public function setConfirmTitle(string $sConfirmTitle): self
    {
        $this->sConfirmTitle = $sConfirmTitle;
        return $this;
    }

    // --------------------------------------------------------------------------

    public function getConfirmBody(): string
    {
        return $this->sConfirmBody;
    }

    // --------------------------------------------------------------------------

    public function setConfirmBody(string $sConfirmBody): self
    {
        $this->sConfirmBody = $sConfirmBody;
        return $this;
    }
}

<?php

namespace Nails\Cdn\Service;

use Nails\Cdn\Constants;
use Nails\Common\Service\Session;

class MediaManager
{
    const SESSION_KEY_DEFAULT = 'MEDIA_MANAGER_DEFAULT';

    public function __construct(
        protected Session $session,
    ) {
    }

    public function setDefault(int $version): static
    {
        $this->session->setUserData(static::SESSION_KEY_DEFAULT, $version);
        return $this;
    }

    public function getUrl(array $query = [], ?int $version = null): string
    {

        $sBaseUrl = match ($version ?? $this->session->getUserData(static::SESSION_KEY_DEFAULT)) {
            Constants::MEDIA_MANAGER_V2 => Constants::MEDIA_MANAGER_V2_URL,
            default => Constants::MEDIA_MANAGER_V1_URL
        };

        if ($query) {
            $sBaseUrl .= '?' . http_build_query($query);
        }

        return siteUrl($sBaseUrl);
    }
}

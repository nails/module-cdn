<?php

namespace Nails\Cdn\Service;

use Nails\Cdn\Constants;
use Nails\Cdn\Exception\CdnException;
use Nails\Common\Service\Session;
use Nails\Config;

class MediaManager
{
    const SESSION_KEY_DEFAULT = 'MEDIA_MANAGER_DEFAULT';

    public function __construct(
        protected Session $session,
    ) {
    }

    public function getVersions(): array
    {
        return [
            Constants::MEDIA_MANAGER_V1,
            Constants::MEDIA_MANAGER_V2,
        ];
    }

    public function isValidVersion(int $version): bool
    {
        return in_array($version, $this->getVersions(), true);
    }

    public function getEnabledVersions(): array
    {
        $enabled = Config::get('CDN_MEDIA_MANAGER_ENABLE');
        if (!is_array($enabled)) {
            $enabled = array_filter([(int) $enabled]);
        }

        $enabled = array_values(
            array_filter(
                $enabled,
                fn(int $version) => $this->isValidVersion($version)
            )
        );

        return empty($enabled)
            ? $this->getVersions()
            : $enabled;
    }

    public function isVersionEnabled(int $version): bool
    {
        return in_array($version, $this->getEnabledVersions(), true);
    }

    /**
     * @throws CdnException
     */
    public function setDefault(int $version): static
    {
        if (!$this->isValidVersion($version)) {
            throw new CdnException('Invalid version provided');
        } elseif (!$this->isVersionEnabled($version)) {
            throw new CdnException(sprintf('Version %d is not enabled', $version));
        }

        $this->session->setUserData(static::SESSION_KEY_DEFAULT, $version);
        return $this;
    }

    /**
     * @throws CdnException
     */
    public function getUrl(array $query = [], string $path = '', ?int $version = null): string
    {
        //  If an explicitly passed version is not enabled, complain
        if ($version && !$this->isVersionEnabled($version)) {
            throw new CdnException(sprintf('Version %d is not enabled', $version));
        }

        if (!$version && $this->session->getUserData(static::SESSION_KEY_DEFAULT)) {
            $version = $this->session->getUserData(static::SESSION_KEY_DEFAULT);
            if (!$this->isVersionEnabled($version)) {
                //  Session version is no longer valid, so reset
                $version = null;
            }
        }

        if (!$version) {
            $enabledVersions = $this->getEnabledVersions();
            $version         = reset($enabledVersions);
            $this->setDefault($version);
        }

        $managerUrl = match ($version) {
            Constants::MEDIA_MANAGER_V1 => Constants::MEDIA_MANAGER_V1_URL,
            Constants::MEDIA_MANAGER_V2 => Constants::MEDIA_MANAGER_V2_URL,
            default => null,
        };

        if (!$managerUrl) {
            throw new CdnException('Unable to determine media manager url');
        }

        if (trim($path)) {
            $managerUrl .= '/' . ltrim($path, '/');
        }

        if ($query) {
            $managerUrl .= '?' . http_build_query($query);
        }

        return siteUrl($managerUrl);
    }
}

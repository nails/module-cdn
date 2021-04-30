<?php

namespace Nails\Cdn\Traits\Database\Seeder;

use Nails\Cdn\Constants;

/**
 * Trait Cdn
 *
 * @package Nails\Cdn\Traits\Database\Seeder
 */
trait Cdn
{
    /**
     * Returns a random ID from a particular model
     *
     * @param string $sModel    The model to use
     * @param string $sProvider The model's provider
     * @param array  $aData     Any data to pass to the model
     *
     * @return int|null
     */
    abstract protected function randomId(string $sModel, string $sProvider, array $aData = []): ?int;

    // --------------------------------------------------------------------------

    /**
     * Returns a random CDN object, optionally of a specific mimetype
     *
     * @param string|null $sMime The mime to filter by
     *
     * @return int|null
     */
    protected function randomCdnObject(string $sMime = null): ?int
    {
        return $this->randomId(
            'Object',
            Constants::MODULE_SLUG,
            [
                'where' => array_filter([
                    $sMime ? ['mime', $sMime] : null,
                ]),
            ]
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a random JPEG image
     *
     * @return int|null
     */
    protected function randomJpeg(): ?int
    {
        return $this->randomCdnObject('image/jpeg');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a random PNG image
     *
     * @return int|null
     */
    protected function randomPng(): ?int
    {
        return $this->randomCdnObject('image/png');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a random GIF image
     *
     * @return int|null
     */
    protected function randomGif(): ?int
    {
        return $this->randomCdnObject('image/gif');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a random JPEG, PNG, or GIF
     *
     * @return int|null
     */
    protected function randomImage(): ?int
    {
        /**
         * Define methods and shuffle so we mix up the type of image returned. If
         * the first method doesn't return anything, try the next.
         */
        $aMethods = [
            'randomJpeg',
            'randomPng',
            'randomGif',
        ];

        shuffle($aMethods);

        foreach ($aMethods as $sMethod) {
            $iObjectId = call_user_func([$this, $sMethod]);
            if (!empty($iObjectId)) {
                return $iObjectId;
            }
        }

        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a random MP3
     *
     * @return int|null
     */
    protected function randomMp3(): ?int
    {
        return $this->randomCdnObject('audio/mpeg');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a random PDF
     *
     * @return int|null
     */
    protected function randomPdf(): ?int
    {
        return $this->randomCdnObject('application/pdf');
    }
}

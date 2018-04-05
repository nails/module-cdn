<?php

/**
 * Permitted Dimension Exception
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Exceptions
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Cdn\Exception;

class PermittedDimensionException extends CdnException
{
    /**
     * The URL for any relevant documentation
     * @var string
     */
    const DOCUMENTATION_URL = 'https://github.com/nailsapp/module-cdn/blob/master/docs/transformation/security.md';
}

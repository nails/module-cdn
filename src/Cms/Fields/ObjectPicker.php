<?php

/**
 * A CDN object picker for WidgetEditor (v2) widgets. Stores the object id, an integer, or null.
 *
 * The control is admin's object picker (cdn/views/picker.php markup, bound by ObjectPicker.js). Its Vue wrapper is
 * in assets/js/widget-editor/, built to admin.widget-editor-fields.min.js, which registers it with the editor.
 *
 * module-cms is not a dependency of this module. The class is only loaded when module-cms discovers field types.
 *
 * @package     Nails
 * @subpackage  module-cdn
 * @category    Field
 * @author      Nails Dev Team
 */

namespace Nails\Cdn\Cms\Fields;

use Nails\Cdn\Constants;
use Nails\Cms\WidgetEditor\Field\Base;

class ObjectPicker extends Base
{
    protected string $bucket = '';

    // --------------------------------------------------------------------------

    public static function getType(): string
    {
        return 'cdn_object';
    }

    // --------------------------------------------------------------------------

    public static function getAssets(): array
    {
        return [['admin.widget-editor-fields.min.js', Constants::MODULE_SLUG]];
    }

    // --------------------------------------------------------------------------

    /**
     * The bucket the media manager opens, by slug
     */
    public function bucket(string $bucket): static
    {
        $this->bucket = $bucket;
        return $this;
    }

    // --------------------------------------------------------------------------

    protected function config(): array
    {
        return ['bucket' => $this->bucket];
    }
}

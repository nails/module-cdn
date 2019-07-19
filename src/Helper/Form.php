<?php

/**
 * Form helper
 *
 * @package     Nails
 * @subpackage  nails/module-cdn
 * @category    Helper
 * @author      Nails Dev Team
 */

namespace Nails\Cdn\Helper;

use Nails\Factory;

class Form
{
    /**
     * Generates a form field containing the media manager to select a file.
     *
     * @param array  $field The config array
     * @param string $tip   An optional tip (DEPRECATED: use $field['tip'] instead)
     *
     * @return string        The form HTML
     * @todo  when form builder is updated, ensure that other things can create custom field types
     *
     */
    public static function form_field_cdn_object_picker($field, $tip = ''): string
    {
        //  Set var defaults
        $_field_id         = isset($field['id']) ? $field['id'] : null;
        $_field_type       = isset($field['type']) ? $field['type'] : 'text';
        $_field_oddeven    = isset($field['oddeven']) ? $field['oddeven'] : null;
        $_field_key        = isset($field['key']) ? $field['key'] : null;
        $_field_label      = isset($field['label']) ? $field['label'] : null;
        $_field_default    = isset($field['default']) ? $field['default'] : null;
        $_field_sub_label  = isset($field['sub_label']) ? $field['sub_label'] : null;
        $_field_required   = isset($field['required']) ? $field['required'] : false;
        $_field_readonly   = isset($field['readonly']) ? $field['readonly'] : false;
        $_field_error      = isset($field['error']) ? $field['error'] : false;
        $_field_class      = isset($field['class']) ? $field['class'] : '';
        $_field_data       = isset($field['data']) ? $field['data'] : [];
        $_field_info       = isset($field['info']) ? $field['info'] : false;
        $_field_info_class = isset($field['info_class']) ? $field['info_class'] : false;
        $_field_tip        = isset($field['tip']) ? $field['tip'] : $tip;

        //  CDN Specific
        $_field_bucket = isset($field['bucket']) ? $field['bucket'] : null;

        $_tip          = [];
        $_tip['class'] = is_array($_field_tip) && isset($_field_tip['class']) ? $_field_tip['class'] : 'fa fa-question-circle fa-lg tip';
        $_tip['rel']   = is_array($_field_tip) && isset($_field_tip['rel']) ? $_field_tip['rel'] : 'tipsy-left';
        $_tip['title'] = is_array($_field_tip) && isset($_field_tip['title']) ? $_field_tip['title'] : null;
        $_tip['title'] = is_string($_field_tip) ? $_field_tip : $_tip['title'];

        $_field_id_top = $_field_id ? 'id="field-' . $_field_id . '"' : '';
        $_error        = form_error($_field_key) || $_field_error ? 'error' : '';
        $_error_class  = $_error ? 'error' : '';
        $_readonly     = $_field_readonly ? 'readonly="readonly"' : '';
        $_readonly_cls = $_field_readonly ? 'readonly' : '';

        // --------------------------------------------------------------------------

        //  Is the label required?
        $_field_label .= $_field_required ? '*' : '';

        //  Prep sublabel
        $_field_sub_label = $_field_sub_label ? '<small>' . $_field_sub_label . '</small>' : '';

        //  Has the field got a tip?
        $_tipclass = $_tip['title'] ? 'with-tip' : '';
        $_tip      = $_tip['title'] ? '<b class="' . $_tip['class'] . '" rel="' . $_tip['rel'] . '" title="' . htmlentities($_tip['title'], ENT_QUOTES) . '"></b>' : '';

        // --------------------------------------------------------------------------

        //  Prep the field's attributes
        $_attr = '';

        //  Does the field have an id?
        $_attr .= $_field_id ? 'id="' . $_field_id . '" ' : '';

        //  Any data attributes?
        foreach ($_field_data as $attr => $value) {

            $_attr .= ' data-' . $attr . '="' . $value . '"';
        }

        // --------------------------------------------------------------------------

        //  Generate the field's HTML
        $sFieldAttr = $_attr;
        $sFieldAttr .= ' class="' . $_field_class . '" ';

        $_field_html = cdnObjectPicker(
            $_field_key,
            $_field_bucket,
            set_value($_field_key, $_field_default),
            $sFieldAttr,
            '',
            !empty($_readonly)
        );

        // --------------------------------------------------------------------------

        //  Errors
        if ($_error && $_field_error) {

            $_error = '<span class="alert alert-danger">' . $_field_error . '</span>';
        } elseif ($_error) {

            $_error = form_error($_field_key, '<span class="alert alert-danger">', '</span>');
        }

        // --------------------------------------------------------------------------

        //  info block
        $info_block = $_field_info ? '<small class="info ' . $_field_info_class . '">' . $_field_info . '</small>' : '';

        // --------------------------------------------------------------------------

        $_out = <<<EOT

    <div class="field $_error_class $_field_oddeven $_readonly_cls $_field_type" $_field_id_top>
        <label>
            <span class="label">
                $_field_label
                $_field_sub_label
            </span>
            <span class="input $_tipclass">
                $_field_html
                $_tip
                $_error
                $info_block
            <span>
        </label>
    </div>

EOT;

        return $_out;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a form field containing multiple object pickers
     *
     * @param array  $field The config array
     * @param string $tip   An optional tip (DEPRECATED: use $field['tip'] instead)
     *
     * @return string        The form HTML
     * @todo  when form builder is updated, ensure that other things can create custom field types
     *
     */
    public static function form_field_cdn_object_picker_multi($field, $tip = ''): string
    {
        //  Set var defaults
        $_field_id         = isset($field['id']) ? $field['id'] : null;
        $_field_type       = isset($field['type']) ? $field['type'] : 'text';
        $_field_oddeven    = isset($field['oddeven']) ? $field['oddeven'] : null;
        $_field_key        = isset($field['key']) ? $field['key'] : null;
        $_field_label      = isset($field['label']) ? $field['label'] : null;
        $_field_default    = isset($field['default']) ? array_filter((array) $field['default']) : [];
        $_field_bucket     = isset($field['bucket']) ? $field['bucket'] : null;
        $_field_sub_label  = isset($field['sub_label']) ? $field['sub_label'] : null;
        $_field_required   = isset($field['required']) ? $field['required'] : false;
        $_field_readonly   = isset($field['readonly']) ? $field['readonly'] : false;
        $_field_error      = isset($field['error']) ? $field['error'] : false;
        $_field_class      = isset($field['class']) ? $field['class'] : '';
        $_field_data       = isset($field['data']) ? $field['data'] : [];
        $_field_info       = isset($field['info']) ? $field['info'] : false;
        $_field_info_class = isset($field['info_class']) ? $field['info_class'] : false;
        $_field_tip        = isset($field['tip']) ? $field['tip'] : $tip;
        $_field_sortable   = isset($field['sortable']) ? $field['sortable'] : false;

        //  CDN Specific
        $_field_bucket = isset($field['bucket']) ? $field['bucket'] : null;

        $_tip          = [];
        $_tip['class'] = is_array($_field_tip) && isset($_field_tip['class']) ? $_field_tip['class'] : 'fa fa-question-circle fa-lg tip';
        $_tip['rel']   = is_array($_field_tip) && isset($_field_tip['rel']) ? $_field_tip['rel'] : 'tipsy-left';
        $_tip['title'] = is_array($_field_tip) && isset($_field_tip['title']) ? $_field_tip['title'] : null;
        $_tip['title'] = is_string($_field_tip) ? $_field_tip : $_tip['title'];

        $_field_id_top = $_field_id ? 'id="field-' . $_field_id . '"' : '';
        $_error        = form_error($_field_key) || $_field_error ? 'error' : '';
        $_error_class  = $_error ? 'error' : '';
        $_readonly     = $_field_readonly ? 'readonly="readonly"' : '';
        $_readonly_cls = $_field_readonly ? 'readonly' : '';

        // --------------------------------------------------------------------------

        //  Is the label required?
        $_field_label .= $_field_required ? '*' : '';

        //  Prep sublabel
        $_field_sub_label = $_field_sub_label ? '<small>' . $_field_sub_label . '</small>' : '';

        //  Has the field got a tip?
        $_tipclass = $_tip['title'] ? 'with-tip' : '';
        $_tip      = $_tip['title'] ? '<b class="' . $_tip['class'] . '" rel="' . $_tip['rel'] . '" title="' . htmlentities($_tip['title'], ENT_QUOTES) . '"></b>' : '';

        // --------------------------------------------------------------------------

        //  Prep the field's attributes
        $_attr = '';

        //  Does the field have an id?
        $_attr .= $_field_id ? 'id="' . $_field_id . '" ' : '';

        //  Any data attributes?
        foreach ($_field_data as $attr => $value) {

            $_attr .= ' data-' . $attr . '="' . $value . '"';
        }

        // --------------------------------------------------------------------------

        //  Generate the field's HTML
        $sFieldAttr = $_attr;
        $sFieldAttr .= ' class="' . $_field_class . '" ';
        $sFieldAttr .= $_readonly;

        // Small hack to inject data-bind into the input.
        $_field_html = cdnObjectPicker(
            '" data-bind="attr:{name: \'download[\' + \$index() + \'][download_id]\'}, value: download_id"',
            $_field_bucket
        );

        // --------------------------------------------------------------------------

        //  Errors
        if ($_error && $_field_error) {

            $_error = '<span class="alert alert-danger">' . $_field_error . '</span>';
        } elseif ($_error) {

            $_error = form_error($_field_key, '<span class="alert alert-danger">', '</span>');
        }

        // --------------------------------------------------------------------------

        //  info block
        $info_block = $_field_info ? '<small class="info ' . $_field_info_class . '">' . $_field_info . '</small>' : '';

        // --------------------------------------------------------------------------

        if ($_field_sortable) {
            $sFieldSortableHandle  = '<td class="handle" width="25"><b class="fa fa-bars"></b></td>';
            $sFieldSortableColspan = 'colspan="2"';
            $sFieldSortableOrder   = '<input type="hidden" name="' . $_field_key . '[{{index}}][order]" value="{{index}}" class="js-admin-sortable__order">';
        } else {
            $sFieldSortableHandle  = '';
            $sFieldSortableColspan = '';
            $sFieldSortableOrder   = '';
        }

        // --------------------------------------------------------------------------

        //  Start generating the markup
        $_field_html_id     = '<input type="hidden" name="' . $_field_key . '[{{index}}][id]" value="{{id}}">';
        $_field_html_object = cdnObjectPicker(
            $_field_key . '[{{index}}][object_id]',
            $_field_bucket,
            '{{object_id}}',
            'data-index="{{index}}"'
        );
        $_field_html_remove = '<a href="#" class="js-cdn-multi-action-remove" data-index="{{index}}">';
        $_field_html_remove .= '<b class="fa fa-lg fa-times-circle text-danger"></b>';
        $_field_html_remove .= '</a>';

        //  JS template
        $jsTpl = <<<EOT
            <tr>
                $sFieldSortableHandle
                <td>
                    $_field_html_id
                    $sFieldSortableOrder
                    $_field_html_object
                </td>
                <td class="text-center">
                    $_field_html_remove
                </td>
            </tr>
EOT;

        //  Generate the initial objects
        $_default_html = '';
        $oMustache     = Factory::service('Mustache');

        if (!empty($_POST)) {

            if (strpos($_field_key, '[') !== false) {

                preg_match_all('/(.+?)\[([a-zA-Z0-9_\]\[]+)\]/', $_field_key, $aKeyBits);

                $aKeyBits[2] = explode('][', $aKeyBits[2][0]);
                $aKeyBits    = array_merge($aKeyBits[1], $aKeyBits[2]);
                $sPostKey    = '$_POST';

                foreach ($aKeyBits as $sKeyBit) {
                    $sPostKey .= '[\'' . $sKeyBit . '\']';
                }

                //  @todo find a way to not be evil
                $aValues = eval('return !empty(' . $sPostKey . ') ? ' . $sPostKey . ' : [];');
            } else {
                $aValues = ArrayHelper::getFromArray($_field_key, $_POST);
            }
        } else {
            $aValues = $_field_default;
        }

        for ($i = 0; $i < count($aValues); $i++) {
            $aValues[$i]          = (array) $aValues[$i];
            $aValues[$i]['index'] = $i;
            $_default_html        .= $oMustache->render($jsTpl, $aValues[$i]);
        }

        $sFieldAttr .= ' data-defaults="' . htmlentities(json_encode($aValues)) . '"';

        // --------------------------------------------------------------------------

        $_out = <<<EOT
            <div class="field cdn-multi cdn-multi-with-label $_error_class $_field_oddeven $_readonly_cls $_field_type" $_field_id_top $sFieldAttr>
                <div>
                    <span class="label">
                        $_field_label
                        $_field_sub_label
                    </span>
                    <span class="input $_tipclass">
                        <table>
                            <thead>
                                <th width="*" $sFieldSortableColspan>File</th>
                                <th width="10"></th>
                            </thead>
                            <tbody class="js-row-target $sFieldSortable">
                                $_default_html
                            </tbody>
                            <tbody>
                                <tr>
                                    <td colspan="3">
                                        <button type="button" class="btn btn-xs btn-success js-cdn-multi-action-add">
                                            <span class="fa fa-plus"></span> Add download
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        $_tip
                        $_error
                        $info_block
                    <span>
                </div>
                <script type="text/x-template" class="js-row-tpl">
                    $jsTpl
                </script>
            </div>
EOT;

        return $_out;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a form field containing multiple object pickers
     *
     * @param array  $aConfig The config array
     * @param string $sTip    An optional tip (DEPRECATED: use $field['tip'] instead)
     *
     * @return string        The form HTML
     * @todo  when form builder is updated, ensure that other things can create custom field types
     *
     */
    public static function form_field_cdn_object_picker_multi_with_label($aConfig, $sTip = ''): string
    {
        //  Set var defaults
        $sFieldId        = ArrayHelper::getFromArray('id', $aConfig, null);
        $sFieldType      = ArrayHelper::getFromArray('type', $aConfig, 'text');
        $sFieldOddEven   = ArrayHelper::getFromArray('oddeven', $aConfig, null);
        $sFieldKey       = ArrayHelper::getFromArray('key', $aConfig, null);
        $sFieldLabel     = ArrayHelper::getFromArray('label', $aConfig, null);
        $sFieldDefault   = array_filter((array) ArrayHelper::getFromArray('default', $aConfig));
        $sFieldSubLabel  = ArrayHelper::getFromArray('sub_label', $aConfig, null);
        $sFieldRequired  = ArrayHelper::getFromArray('required', $aConfig, false);
        $sFieldReadonly  = ArrayHelper::getFromArray('readonly', $aConfig, false);
        $sFieldError     = ArrayHelper::getFromArray('error', $aConfig, false);
        $sFieldClass     = ArrayHelper::getFromArray('class', $aConfig, '');
        $sFieldData      = ArrayHelper::getFromArray('data', $aConfig, []);
        $sFieldInfo      = ArrayHelper::getFromArray('info', $aConfig, false);
        $sFieldInfoClass = ArrayHelper::getFromArray('info_class', $aConfig, false);
        $sFieldTip       = ArrayHelper::getFromArray('tip', $aConfig, $sTip);
        $sFieldSortable  = ArrayHelper::getFromArray('sortable', $aConfig, false);

        $sFieldObjectKey        = ArrayHelper::getFromArray('object_key', $aConfig, 'object_id');
        $sFieldTableLabelObject = ArrayHelper::getFromArray('table_label_object', $aConfig, 'File');

        $sFieldLabelKey        = ArrayHelper::getFromArray('label_key', $aConfig, 'label');
        $sFieldTableLabelLabel = ArrayHelper::getFromArray('table_label_label', $aConfig, 'Label');

        //  CDN Specific
        $sFieldBucket = isset($aConfig['bucket']) ? $aConfig['bucket'] : null;

        $aTip = [
            'class' => ArrayHelper::getFromArray('class', (array) $sFieldTip, 'fa fa-question-circle fa-lg tip'),
            'rel'   => ArrayHelper::getFromArray('rel', (array) $sFieldTip, 'tipsy-left'),
            'title' => ArrayHelper::getFromArray('title', (array) $sFieldTip, $sFieldTip),
        ];

        $sFieldIdTop    = $sFieldId ? 'id="field-' . $sFieldId . '"' : '';
        $sError         = form_error($sFieldKey) || $sFieldError ? 'error' : '';
        $sErrorClass    = $sError ? 'error' : '';
        $sReadonly      = $sFieldReadonly ? 'readonly="readonly"' : '';
        $sReadonlyClass = $sFieldReadonly ? 'readonly' : '';

        // --------------------------------------------------------------------------

        //  Is the label required?
        $sFieldLabel .= $sFieldRequired ? '*' : '';

        //  Prep sublabel
        $sFieldSubLabel = $sFieldSubLabel ? '<small>' . $sFieldSubLabel . '</small>' : '';

        //  Has the field got a tip?
        $sTipClass = $aTip['title'] ? 'with-tip' : '';
        $sTip      = $aTip['title'] ? '<b class="' . $aTip['class'] . '" rel="' . $aTip['rel'] . '" title="' . htmlentities($aTip['title'], ENT_QUOTES) . '"></b>' : '';

        // --------------------------------------------------------------------------

        //  Prep the field's attributes
        $sFieldAttr = '';

        //  Does the field have an id?
        $sFieldAttr .= $sFieldId ? 'id="' . $sFieldId . '" ' : '';

        //  Any data attributes?
        foreach ($sFieldData as $attr => $value) {
            $sFieldAttr .= ' data-' . $attr . '="' . $value . '"';
        }

        // --------------------------------------------------------------------------

        //  Generate the field's HTML
        $sFieldAttr .= ' class="' . $sFieldClass . '" ';
        $sFieldAttr .= $sReadonly;

        // --------------------------------------------------------------------------

        //  Errors
        if ($sError && $sFieldError) {
            $sError = '<span class="alert alert-danger">' . $sFieldError . '</span>';
        } elseif ($sError) {
            $sError = form_error($sFieldKey, '<span class="alert alert-danger">', '</span>');
        }

        // --------------------------------------------------------------------------

        //  info block
        $sInfoBlock = $sFieldInfo ? '<small class="info ' . $sFieldInfoClass . '">' . $sFieldInfo . '</small>' : '';

        // --------------------------------------------------------------------------

        if ($sFieldSortable) {
            $sFieldSrotableClass   = 'js-admin-sortable';
            $sFieldSortableHandle  = '<td class="handle" width="25"><b class="fa fa-bars"></b></td>';
            $sFieldSortableColspan = 'colspan="2"';
            $sFieldSortableOrder   = '<input type="hidden" name="' . $sFieldKey . '[{{index}}][order]" value="{{index}}" class="js-admin-sortable__order">';
        } else {
            $sFieldSrotableClass   = '';
            $sFieldSortableHandle  = '';
            $sFieldSortableColspan = '';
            $sFieldSortableOrder   = '';
        }

        // --------------------------------------------------------------------------

        //  Start generating the markup
        $sFieldHtmlId     = '<input type="hidden" name="' . $sFieldKey . '[{{index}}][id]" value="{{id}}">';
        $sFieldHtmlObject = cdnObjectPicker(
            $sFieldKey . '[{{index}}][' . $sFieldObjectKey . ']',
            $sFieldBucket,
            '{{' . $sFieldObjectKey . '}}',
            'data-index="{{index}}"'
        );
        $sFieldHtmlLabel  = '<input type="text" name="' . $sFieldKey . '[{{index}}][' . $sFieldLabelKey . ']" value="{{' . $sFieldLabelKey . '}}" data-index="{{index}}" class="js-label">';
        $sFieldHtmlRemove = '<a href="#" class="js-cdn-multi-action-remove" data-index="{{index}}">';
        $sFieldHtmlRemove .= '<b class="fa fa-lg fa-times-circle text-danger"></b>';
        $sFieldHtmlRemove .= '</a>';

        //  JS template
        $jsTpl = <<<EOT
            <tr>
                $sFieldSortableHandle
                <td>
                    $sFieldHtmlId
                    $sFieldSortableOrder
                    $sFieldHtmlObject
                </td>
                <td>
                    $sFieldHtmlLabel
                </td>
                <td class="text-center">
                    $sFieldHtmlRemove
                </td>
            </tr>
EOT;

        //  Generate the initial objects
        $sDefaultHtml = '';
        $oMustache    = Factory::service('Mustache');

        if (!empty($_POST)) {

            if (strpos($sFieldKey, '[') !== false) {

                preg_match_all('/(.+?)\[([a-zA-Z0-9_\]\[]+)\]/', $sFieldKey, $aKeyBits);

                $aKeyBits[2] = explode('][', $aKeyBits[2][0]);
                $aKeyBits    = array_merge($aKeyBits[1], $aKeyBits[2]);
                $sPostKey    = '$_POST';

                foreach ($aKeyBits as $sKeyBit) {
                    $sPostKey .= '[\'' . $sKeyBit . '\']';
                }

                //  @todo find a way to not be evil
                $aValues = eval('return !empty(' . $sPostKey . ') ? ' . $sPostKey . ' : [];');
            } else {
                $aValues = ArrayHelper::getFromArray($sFieldKey, $_POST, []);
            }

        } else {
            $aValues = $sFieldDefault;
        }

        for ($i = 0; $i < count($aValues); $i++) {
            $aValues[$i]          = (array) $aValues[$i];
            $aValues[$i]['index'] = $i;
            $sDefaultHtml         .= $oMustache->render($jsTpl, $aValues[$i]);
        }

        $sFieldAttr .= ' data-defaults="' . htmlentities(json_encode($aValues)) . '"';
        $sFieldAttr .= ' data-label-key="' . $sFieldLabelKey . '"';

        // --------------------------------------------------------------------------

        $_out = <<<EOT
            <div class="field cdn-multi cdn-multi-with-label $sErrorClass $sFieldOddEven $sReadonlyClass $sFieldType" $sFieldIdTop $sFieldAttr>
                <div>
                    <span class="label">
                        $sFieldLabel
                        $sFieldSubLabel
                    </span>
                    <span class="input $sTipClass">
                        <table>
                            <thead>
                                <th width="300" $sFieldSortableColspan>$sFieldTableLabelObject</th>
                                <th width="*">$sFieldTableLabelLabel</th>
                                <th width="10"></th>
                            </thead>
                            <tbody class="js-row-target $sFieldSrotableClass">
                                $sDefaultHtml
                            </tbody>
                            <tbody>
                                <tr>
                                    <td colspan="4">
                                        <button type="button" class="btn btn-xs btn-success js-cdn-multi-action-add">
                                            <span class="fa fa-plus"></span> Add download
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        $sTip
                        $sError
                        $sInfoBlock
                    <span>
                </div>
                <script type="text/x-template" class="js-row-tpl">
                    $jsTpl
                </script>
            </div>
EOT;

        return $_out;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a form field containing the media manager to select a file.
     *
     * @param array  $aField The config array
     * @param string $sTip   An optional tip (DEPRECATED: use $field['tip'] instead)
     *
     * @return string         The form HTML
     * @deprecated Use form_field_cdn_object_picker instead
     *
     */
    public static function form_field_mm($aField, $sTip = ''): string
    {
        deprecatedError('form_field_mm', 'form_field_cdn_object_picker');
        return static::form_field_cdn_object_picker($aField, $sTip);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a form field containing the media manager to select an image
     *
     * @param array  $aField The config array
     * @param string $sTip   An optional tip (DEPRECATED: use $field['tip'] instead)
     *
     * @return string         The form HTML
     * @deprecated Use form_field_cdn_object_picker instead
     *
     */
    public static function form_field_mm_image($aField, $sTip = ''): string
    {
        deprecatedError('form_field_mm_image', 'form_field_cdn_object_picker');
        return static::form_field_cdn_object_picker($aField, $sTip);
    }
}

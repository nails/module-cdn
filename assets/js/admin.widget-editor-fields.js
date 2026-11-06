/**
 * Registers this module's field types with the CMS widget editor (module-cms). Loaded only on pages with the
 * editor, which provides Vue; see Nails\Cdn\Cms\Fields\ObjectPicker::getAssets().
 */
import ObjectPickerControl from './widget-editor/ObjectPickerControl.vue';
import { OBJECT_PICKER_RULES } from './widget-editor/object-picker-rules.js';

(window.NAILS_CMS_WIDGET_EDITOR_FIELDS = window.NAILS_CMS_WIDGET_EDITOR_FIELDS || []).push({
    type: 'cdn_object',
    component: ObjectPickerControl,
    ...OBJECT_PICKER_RULES,
});

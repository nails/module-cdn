/**
 * The rules of the `cdn_object` field type (Nails\Cdn\Cms\Fields\ObjectPicker), without its component, so they can
 * be tested without Vue
 */
export const OBJECT_PICKER_RULES = {
    layout: 'block',
    // A positive integer object id, or null
    coerce: (raw) => {
        const id = parseInt(raw, 10);
        return Number.isInteger(id) && id > 0 ? id : null;
    },
    isEmpty: (value) => !value,
    summary: (value) => (value ? 'Object #' + value : 'None selected'),
};

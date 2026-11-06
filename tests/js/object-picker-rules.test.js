import { describe, expect, it } from 'vitest';
import { OBJECT_PICKER_RULES } from '../../assets/js/widget-editor/object-picker-rules.js';

describe('cdn_object field rules', () => {

    it('stores a positive integer id, or null', () => {
        expect(OBJECT_PICKER_RULES.coerce('12')).toBe(12);
        expect(OBJECT_PICKER_RULES.coerce(12)).toBe(12);
        expect(OBJECT_PICKER_RULES.coerce('0')).toBeNull();
        expect(OBJECT_PICKER_RULES.coerce('')).toBeNull();
        expect(OBJECT_PICKER_RULES.coerce(null)).toBeNull();
    });

    it('is empty without an id', () => {
        expect(OBJECT_PICKER_RULES.isEmpty(null)).toBe(true);
        expect(OBJECT_PICKER_RULES.isEmpty(7)).toBe(false);
    });

    it('summarises the selection in one line', () => {
        expect(OBJECT_PICKER_RULES.summary(7)).toBe('Object #7');
        expect(OBJECT_PICKER_RULES.summary(null)).toBe('None selected');
    });

    it('lays the picker out as a block, because it has several controls', () => {
        expect(OBJECT_PICKER_RULES.layout).toBe('block');
    });
});

'use strict';

import '../sass/admin.scss';
import ObjectPicker from './components/ObjectPicker.js';
import UtilitiesOrphans from './components/UtilitiesOrphans.js';

(function() {
    window.NAILS.ADMIN.registerPlugin(
        'nails/module-cdn',
        'ObjectPicker',
        new ObjectPicker()
    );
    window.NAILS.ADMIN.registerPlugin(
        'nails/module-cdn',
        'UtilitiesOrphans',
        new UtilitiesOrphans()
    );
})();

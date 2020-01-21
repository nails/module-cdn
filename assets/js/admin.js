'use strict';

import '../sass/admin.scss';
import ObjectPicker from './components/ObjectPicker.js';
import UtilitiesOrphans from './components/UtilitiesOrphans.js';

(function() {
    window.NAILS.ADMIN.registerPlugin(
        'nails/module-cdn',
        'ObjectPicker',
        function(controller) {
            return new ObjectPicker(controller);
        }
    );
    window.NAILS.ADMIN.registerPlugin(
        'nails/module-cdn',
        'UtilitiesOrphans',
        function(controller) {
            return new UtilitiesOrphans(controller);
        }
    );
})();

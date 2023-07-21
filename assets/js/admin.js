'use strict';

import '../sass/admin.scss';
import ObjectPicker from './components/ObjectPicker.js';

(function() {
    window.NAILS.ADMIN.registerPlugin(
        'nails/module-cdn',
        'ObjectPicker',
        function(controller) {
            return new ObjectPicker(controller);
        }
    );
})();

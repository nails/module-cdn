'use strict';

import '../sass/admin.scss';
import MediaManagerV2 from './components/MediaManagerV2.js';
import ObjectPicker from './components/ObjectPicker.js';

(function() {
    window.NAILS.ADMIN.registerPlugin(
        'nails/module-cdn',
        'MediaManagerV2',
        function(controller) {
            return new MediaManagerV2(controller);
        }
    );
    window.NAILS.ADMIN.registerPlugin(
        'nails/module-cdn',
        'ObjectPicker',
        function(controller) {
            return new ObjectPicker(controller);
        }
    );
})();

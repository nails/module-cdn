'use strict';

import '../sass/admin.scss';
import ObjectPicker from './components/ObjectPicker.js';
import UtilitiesOrphans from './components/UtilitiesOrphans.js';

(function() {
    //  Add this instance to the global scope so outside parties can access it
    window.NAILS.CDN = {
        ObjectPicker: new ObjectPicker()
    };
    new UtilitiesOrphans();
})();

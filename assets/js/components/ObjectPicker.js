class ObjectPicker {

    // --------------------------------------------------------------------------

    constructor(adminController) {

        ObjectPicker.log('Constructing');

        /**
         * Contains reference to the active picker - i.e., the one which the CDN manager is attached to
         * @type {Object}
         */
        this.activePicker = null;

        // --------------------------------------------------------------------------

        /**
         * A cache of objects to avoid hitting the API again
         * @type {Array}
         */
        this.objectCache = [];

        //  Set things up
        this.setupListeners();

        adminController.onRefreshUi(() => {
            this.initPickers();
        });
    };

    // --------------------------------------------------------------------------

    /**
     * Binds the listeners
     * @return {void}
     */
    setupListeners() {

        ObjectPicker.log('Setting up listeners');

        $(document)
            .on('click', '.cdn-object-picker', (e) => {
                let $el = $(e.currentTarget);
                this.openManager($el);
                $el.trigger('opened');
                return false;
            })
            .on('click', '.cdn-object-picker .cdn-object-picker__remove', (e) => {
                let $el = $(e.currentTarget);
                $el.closest('.cdn-object-picker').trigger('removed');
                this.resetPicker($el.closest('.cdn-object-picker'));
                return false;
            })
            .on('click', '.cdn-object-picker .cdn-object-picker__preview-link', (e) => {
                let $el = $(e.currentTarget);
                if ($.fn.fancybox) {
                    $el.closest('.cdn-object-picker').trigger('preview');
                    $.fancybox.open(
                        $el.attr('href'),
                        {
                            'helpers': {
                                'overlay': {
                                    'locked': false
                                }
                            }
                        }
                    );
                } else {
                    $el.closest('.cdn-object-picker').trigger('opened');
                    this.openManager($el.closest('.cdn-object-picker'));
                }
                return false;
            })
            .on('refresh', '.cdn-object-picker', (e) => {
                let $el = $(e.currentTarget);
                this.refreshPicker($el);
                return false;
            });
    };

    // --------------------------------------------------------------------------

    /**
     * Processes CDN pickers and populates them if they have an object ID
     * @return {void}
     */
    initPickers() {
        ObjectPicker.log('Processing new CDN Pickers');
        this.refreshPicker(
            $('.cdn-object-picker:not(.cdn-object-picker--pending):not(.cdn-object-picker--ready)')
        );
    };

    // --------------------------------------------------------------------------

    /**
     * Refresh the matched pickers
     * @param  {Object} elements A jQuery object of pickers
     * @return {void}
     */
    refreshPicker(elements) {

        let fetchIds = [];

        elements.addClass('cdn-object-picker--pending');
        elements.addClass('cdn-object-picker--ready');

        elements.each((index, el) => {
            let $el = $(el);
            let iObjectId = $el.find('.cdn-object-picker__input').val();
            if (iObjectId) {
                fetchIds.push(iObjectId);
            } else {
                $el.removeClass('cdn-object-picker--pending');
            }
        });

        if (fetchIds.length > 0) {

            $.ajax({
                'url': window.SITE_URL + 'api/cdn/object',
                'data': {
                    'ids': fetchIds.join(','),
                    'urls': '150x150-crop'
                }
            })
                .done((data) => {

                    elements.each((index, el) => {
                        let $el = $(el);
                        let iObjectId = parseInt($el.find('.cdn-object-picker__input').val(), 10);
                        for (let i = data.data.length - 1; i >= 0; i--) {
                            if (iObjectId === data.data[i].id) {
                                this.setPickerObject($el, data.data[i]);
                                return;
                            }
                        }
                    });

                    elements.removeClass('cdn-object-picker--pending');
                })
                .fail((data) => {

                    let _data;
                    try {
                        _data = JSON.parse(data.responseText);
                    } catch (e) {
                        _data = {
                            'status': 500,
                            'error': 'An unknown error occurred.'
                        };
                    }

                    ObjectPicker.warn(_data.error);
                });
        }
    };

    // --------------------------------------------------------------------------

    setPickerObject(picker, object) {

        this.resetPicker(picker);

        ObjectPicker.log('Setting picker object');
        let input = picker.find('.cdn-object-picker__input');
        let sizeHuman = this.getReadableFileSizeString(object.object.size.bytes);
        let label = object.object.name;
        let attributes = [
            sizeHuman
        ];

        input.val(object.id);

        if (object.is_img) {
            picker.addClass('cdn-object-picker--has-image');
            picker.find('.cdn-object-picker__preview-link').attr('href', object.url.src);
            picker.find('.cdn-object-picker__preview').css({
                'background-image': 'url(' + object.url['150x150-crop'] + ')'
            });

            attributes.push(object.img.width + 'x' + object.img.height)
        }

        label = label + ' (' + attributes.join(', ') + ')';

        picker.addClass('cdn-object-picker--has-file');
        picker.find('.cdn-object-picker__label')
            .html(label)
            .attr('title', label);

        input.trigger('change');
        picker.trigger('picked');
    };

    // --------------------------------------------------------------------------

    /**
     * Resets a picker back to a default state
     * @param  {Object} picker The picker element
     * @return void
     */
    resetPicker(picker) {
        ObjectPicker.log('Resetting picker');
        picker.removeClass('cdn-object-picker--has-image cdn-object-picker--has-file');
        picker.find('.cdn-object-picker__preview-link').attr('href', '#');
        picker.find('.cdn-object-picker__preview').removeAttr('style');
        picker.find('.cdn-object-picker__label').html('');
        picker.find('.cdn-object-picker__input').val('').trigger('change');
        picker.trigger('reset');
    };

    // --------------------------------------------------------------------------

    /**
     * Opens the CDN Manager window
     * @return {void}
     */
    openManager(picker) {

        if (picker.data('readonly')) {
            return false;
        }

        picker.addClass('cdn-object-picker--pending');
        ObjectPicker.log('Getting Manager URL');
        $.ajax({
            'url': window.SITE_URL + 'api/cdn/manager/url',
            'data': {
                'bucket': picker.data('bucket'),
                'callback': ['NAILS.ADMIN.instances["nails/module-cdn"].ObjectPicker', 'receiveFromManager']
            }
        })
            .done((data) => {
                if ($.fancybox) {
                    ObjectPicker.log('Showing Manager');
                    this.activePicker = picker;
                    $('body').addClass('noscroll');
                    $.fancybox.open(
                        data.data + '&isModal=1',
                        {
                            'type': 'iframe',
                            'width': '95%',
                            'height': '95%',
                            'iframe': {
                                'preload': false // fixes issue with iframe and IE
                            },
                            'helpers': {
                                'overlay': {
                                    'locked': false
                                }
                            },
                            'beforeClose': function() {
                                $('body').removeClass('noscroll');
                            }
                        }
                    );
                } else {
                    ObjectPicker.warn('Fancybox not enabled.');
                }
            })
            .fail((data) => {
                let _data;
                try {

                    _data = JSON.parse(data.responseText);

                } catch (e) {

                    _data = {
                        'status': 500,
                        'error': 'An unknown error occurred.'
                    };
                }

                ObjectPicker.warn(_data.error);
            })
            .always(() => {
                picker.removeClass('cdn-object-picker--pending');
            });
    };

    // --------------------------------------------------------------------------

    receiveFromManager(id) {

        ObjectPicker.log('Received data from manager');
        this.activePicker.addClass('cdn-object-picker--pending');

        //  Check the Object Cache
        let cache = this.getFromCache(id);

        if (cache) {

            ObjectPicker.log('Using Cache');
            this.setPickerObject(this.activePicker, cache);
            this.activePicker.removeClass('cdn-object-picker--pending');
            this.activePicker = null;

        } else {

            ObjectPicker.log('Requesting Object data');
            $.ajax({
                'url': window.SITE_URL + 'api/cdn/object',
                'data': {
                    'id': id,
                    'urls': '150x150-crop'
                }
            })
                .done((data) => {
                    this.setObjectCache(data.data);
                    this.setPickerObject(this.activePicker, data.data);
                })
                .fail((data) => {

                    let _data;
                    try {
                        _data = JSON.parse(data.responseText);
                    } catch (e) {
                        _data = {
                            'status': 500,
                            'error': 'An unknown error occurred.'
                        };
                    }

                    ObjectPicker.warn(_data.error);
                })
                .always(() => {
                    this.activePicker.removeClass('cdn-object-picker--pending');
                    this.activePicker = null;
                });
        }
    };

    // --------------------------------------------------------------------------

    /**
     * Returns a human-readable file size from the given bytes
     * hat-tip: http://stackoverflow.com/questions/10420352/converting-file-size-in-bytes-to-human-readable
     * @param  {Number} fileSizeInBytes The filesize to convert, in bytes
     * @return {String}
     */
    getReadableFileSizeString(fileSizeInBytes) {
        let i = -1;
        let byteUnits = [' kB', ' MB', ' GB', ' TB', 'PB', 'EB', 'ZB', 'YB'];
        do {
            fileSizeInBytes = fileSizeInBytes / 1024;
            i++;
        } while (fileSizeInBytes > 1024);

        return Math.max(fileSizeInBytes, 0.1).toFixed(1) + byteUnits[i];
    };

    // --------------------------------------------------------------------------

    /**
     * Fetches an object from the object cache
     * @param  {Number} objectId The ID of the object to fetch
     * @return {Object}
     */
    getFromCache(objectId) {
        for (let i = this.objectCache.length - 1; i >= 0; i--) {
            if (this.objectCache[i].id === objectId) {
                return this.objectCache[i];
            }
        }

        return null;
    };

    // --------------------------------------------------------------------------

    /**
     * Save an object to the cache
     * @param {Object} object The object to save
     */
    setObjectCache(object) {
        this.objectCache.push(object);
    };

    // --------------------------------------------------------------------------

    /**
     * Write a log to the console
     * @return {void}
     */
    static log() {
        if (typeof (console.log) === 'function') {
            console.log("\x1b[33m[CDN ObjectPicker]\x1b[0m", ...arguments);
        }
    };

    // --------------------------------------------------------------------------

    /**
     * Write a warning to the console
     * @return {void}
     */
    static warn() {
        if (typeof (console.warn) === 'function') {
            console.warn("\x1b[33m[CDN ObjectPicker]\x1b[0m", ...arguments);
        }
    };
}

export default ObjectPicker;

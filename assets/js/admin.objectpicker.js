/* globals console */
var _CDN_OBJECTPICKER;
_CDN_OBJECTPICKER = function()
{
    var base = this;

    // --------------------------------------------------------------------------

    /**
     * Contains reference to the active picker - i.e., the one which the CDN manager is attached to
     * @type {Object}
     */
    base.activePicker = null;

    // --------------------------------------------------------------------------

    /**
     * A cache of objects to avoid hitting the API again
     * @type {Array}
     */
    base.objectCache = [];

    // --------------------------------------------------------------------------

    base.__construct =function() {

        base.log('Constructing');
        base.setupListeners();
        base.initPickers();
        return base;
    };

    // --------------------------------------------------------------------------

    /**
     * Binds the listeners
     * @return {Void}
     */
    base.setupListeners = function() {

        base.log('Setting up listeners');

        $(document).on('click', '.cdn-object-picker', function() {
            base.openManager($(this));
            $(this).trigger('opened');
            return false;
        });

        $(document).on('click', '.cdn-object-picker .cdn-object-picker__remove', function() {
            $(this).closest('.cdn-object-picker').trigger('removed');
            base.resetPicker($(this).closest('.cdn-object-picker'));
            return false;
        });

        $(document).on('click', '.cdn-object-picker .cdn-object-picker__preview-link', function() {
            if ($.fn.fancybox) {
                $(this).closest('.cdn-object-picker').trigger('preview');
                $.fancybox.open(
                    $(this).attr('href'),
                    {
                        'helpers': {
                            'overlay': {
                                'locked': false
                            }
                        }
                    }
                );
            } else {
                $(this).closest('.cdn-object-picker').trigger('opened');
                base.openManager($(this).closest('.cdn-object-picker'));
            }
            return false;
        });

        $(document).on('refresh', '.cdn-object-picker', function() {
            base.refreshPicker($(this));
            return false;
        });
    };

    // --------------------------------------------------------------------------

    /**
     * Processes CDN pickers and populates them if they have an object ID
     * @return {Void}
     */
    base.initPickers = function() {

        base.log('Processing new CDN Pickers');
        base.refreshPicker(
            $('.cdn-object-picker:not(.cdn-object-picker--pending)')
        );
    };

    // --------------------------------------------------------------------------

    /**
     * Refresh the matched pickers
     * @param  {Object} elements A jQuery object of pickers
     * @return {Void}
     */
    base.refreshPicker = function(elements) {

        var fetchIds = [];

        elements.each(function() {
            $(this).addClass('cdn-object-picker--pending');
            var iObjectId = $(this).find('.cdn-object-picker__input').val();
            if (iObjectId) {

                fetchIds.push(iObjectId);

            } else {

                $(this).removeClass('cdn-object-picker--pending');
            }
        });

        if (fetchIds.length > 0) {

            $.ajax({
                'url': window.SITE_URL + 'api/cdn/object',
                'data' : {
                    'ids': fetchIds.join(','),
                    'urls': '150x150-crop'
                }
            })
            .done(function(data) {

                elements.each(function() {
                    var iObjectId = parseInt($(this).find('.cdn-object-picker__input').val(), 10);
                    for (var i = data.data.length - 1; i >= 0; i--) {
                        if (iObjectId === data.data[i].id) {
                            base.setPickerObject($(this), data.data[i]);
                            return;
                        }
                    }
                });

                elements.removeClass('cdn-object-picker--pending');
            })
            .fail(function(data) {

                var _data;
                try {

                    _data = JSON.parse(data.responseText);

                } catch (e) {

                    _data = {
                        'status': 500,
                        'error': 'An unknown error occurred.'
                    };
                }

                base.warn(_data.error);
            });
        }
    };

    // --------------------------------------------------------------------------

    base.setPickerObject = function(picker, object) {

        base.resetPicker(picker);

        base.log('Setting picker object');
        picker.find('.cdn-object-picker__input').val(object.id);
        picker.trigger('picked');

        if (object.isImg) {
            picker.addClass('cdn-object-picker--has-image');
            picker.find('.cdn-object-picker__preview-link').attr('href', object.url.src);
            picker.find('.cdn-object-picker__preview').css(
                {
                    'background-image': 'url(' + object.url['CROP-150x150'] + ')'
                }
            );
        } else {

            var sizeHuman = base.getReadableFileSizeString(object.object.size.bytes);

            picker.addClass('cdn-object-picker--has-file');
            picker.find('.cdn-object-picker__label')
                .html(object.object.name + ' (' + sizeHuman + ')')
                .attr('title', object.object.name + ' (' + sizeHuman + ')');
        }
    };

    // --------------------------------------------------------------------------

    /**
     * Resets a picker back to a default state
     * @param  {Object} picker The picker element
     * @return void
     */
    base.resetPicker = function(picker) {
        base.log('Resetting picker');
        picker.removeClass('cdn-object-picker--has-image cdn-object-picker--has-file');
        picker.find('.cdn-object-picker__preview-link').attr('href', '#');
        picker.find('.cdn-object-picker__preview').removeAttr('style');
        picker.find('.cdn-object-picker__label').html('');
        picker.find('.cdn-object-picker__input').val('');
    };

    // --------------------------------------------------------------------------

    /**
     * Opens the CDN Manager window
     * @return {Void}
     */
    base.openManager = function(picker) {

        picker.addClass('cdn-object-picker--pending');

        base.log('Getting Manager URL');
        $.ajax({
            'url': window.SITE_URL + 'api/cdn/manager/url',
            'data' : {
                'bucket': picker.data('bucket'),
                'callback': ['_CDN_OBJECTPICKER', 'receiveFromManager']
            }
        })
        .done(function(data) {
            if ($.fancybox) {
                base.log('Showing Manager');
                base.activePicker = picker;
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
                        'beforeClose': function()
                        {
                            $('body').removeClass('noscroll');
                        }
                    }
                );
            } else {
                base.warn('Fancybox not enabled.');
            }
        })
        .fail(function(data) {
            var _data;
            try {

                _data = JSON.parse(data.responseText);

            } catch (e) {

                _data = {
                    'status': 500,
                    'error': 'An unknown error occurred.'
                };
            }

            base.warn(_data.error);
        })
        .always(function() {
            picker.removeClass('cdn-object-picker--pending');
        });
    };

    // --------------------------------------------------------------------------

    base.receiveFromManager = function(bucket, filename, id) {

        base.log('Received data from manager');
        base.activePicker.addClass('cdn-object-picker--pending');

        //  Check the Object Cache
        var cache = base.getFromCache(id);

        if (cache) {

            base.log('Using Cache');
            base.setPickerObject(base.activePicker, cache);
            base.activePicker.removeClass('cdn-object-picker--pending');
            base.activePicker = null;

        } else {

            base.log('Requesting Object data');
            $.ajax({
                'url': window.SITE_URL + 'api/cdn/object',
                'data' : {
                    'id': id,
                    'urls': '150x150-crop'
                }
            })
            .done(function(data) {
                base.setObjectCache(data.data);
                base.setPickerObject(base.activePicker, data.data);
            })
            .fail(function(data) {

                var _data;
                try {

                    _data = JSON.parse(data.responseText);

                } catch (e) {

                    _data = {
                        'status': 500,
                        'error': 'An unknown error occurred.'
                    };
                }

                base.warn(_data.error);
            })
            .always(function() {
                base.activePicker.removeClass('cdn-object-picker--pending');
                base.activePicker = null;
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
    base.getReadableFileSizeString = function(fileSizeInBytes) {
        var i = -1;
        var byteUnits = [' kB', ' MB', ' GB', ' TB', 'PB', 'EB', 'ZB', 'YB'];
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
    base.getFromCache = function(objectId) {
        for (var i = base.objectCache.length - 1; i >= 0; i--) {
            if (base.objectCache[i].id === objectId) {
                return base.objectCache[i];
            }
        }

        return null;
    };

    // --------------------------------------------------------------------------

    /**
     * Save an object to the cache
     * @param {Object} object The object to save
     */
    base.setObjectCache = function(object) {
        base.objectCache.push(object);
    };

    // --------------------------------------------------------------------------

    /**
     * Write a log to the console
     * @param  {String} message The message to log
     * @param  {Mixed}  payload Any additional data to display in the console
     * @return {Void}
     */
    base.log = function(message, payload)
    {
        if (typeof(console.log) === 'function') {

            if (payload !== undefined) {

                console.log('CDN Object Picker:', message, payload);

            } else {

                console.log('CDN Object Picker:', message);
            }
        }
    };

    // --------------------------------------------------------------------------

    /**
     * Write a warning to the console
     * @param  {String} message The message to warn
     * @param  {Mixed}  payload Any additional data to display in the console
     * @return {Void}
     */
    base.warn = function(message, payload)
    {
        if (typeof(console.warn) === 'function') {

            if (payload !== undefined) {

                console.warn('CDN Object Picker:', message, payload);

            } else {

                console.warn('CDN Object Picker:', message);
            }
        }
    };

    // --------------------------------------------------------------------------

    return base.__construct();
}();
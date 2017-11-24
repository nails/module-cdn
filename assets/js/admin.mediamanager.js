/* globals ko, $ */
function MediaManager(initialBucket, callbackHandler, callback, isModal) {
    let base = this;

    // --------------------------------------------------------------------------

    base.ready = ko.observable(false);
    base.buckets = ko.observableArray();
    base.objects = ko.observableArray();
    base.currentBucket = ko.observable();
    base.currentPage = ko.observable(1);
    base.showLoadMore = ko.observable(false);
    base.showAddBucket = ko.observable(false);
    base.droppable = ko.observable(false);
    base.showInsert = ko.observable(callback.length > 0);
    base.canUpload = ko.observable(true);
    base.isSearching = ko.observable(false);
    base.searchTimeout = null;
    base.searchTerm = ko.observable();
    base.lastSearch = ko.observable();
    base.isTrash = ko.observable(false);

    // --------------------------------------------------------------------------

    /**
     * An easy to read keymap
     * @type {Object}
     */
    base.keymap = {
        'ENTER': 13,
        'ESC': 27
    };

    // --------------------------------------------------------------------------

    /**
     * Construct the Media Manager
     * @returns {MediaManager} An instance of the class, for chaining
     * @private
     */
    base.__construct = function() {
        base.debug('Constructing');
        base.init();
        return base;
    };

    // --------------------------------------------------------------------------

    /**
     * Initialises the manager, loading up the default or requested bucket
     * @returns {void}
     */
    base.init = function() {
        base.debug('Initialising');
        base.listBuckets()
            .done(function() {
                //  If bucket is defined, then set it as current, else take the first bucket in the list
                if (initialBucket) {
                    let bucket = base.getBucketBySlug(initialBucket);
                    base.currentBucket(bucket.id);
                } else {
                    base.currentBucket(base.buckets()[0].id);
                }
                base.listObjects()
                    .done(function() {
                        base.ready(true);
                    });
            });
    };

    // --------------------------------------------------------------------------

    /**
     * Adds a new bucket to the list
     * @param {object} bucket The bucket details
     * @returns {void}
     */
    base.addBucket = function(bucket) {
        base.debug('Adding bucket:' + bucket.label);
        base.buckets.push({
            'id': bucket.id || null,
            'slug': bucket.slug || null,
            'label': bucket.label || null,
            'max_size': bucket.max_size || null,
            'max_size_human': bucket.max_size_human || null,
            'is_selected': ko.computed(function() {
                return !base.isSearching() && !base.isTrash() && bucket.id === base.currentBucket();
            })
        });
    };

    // --------------------------------------------------------------------------

    /**
     * Changes the actively selected bucket and re-renders the object list
     * @param {object|number} bucket The bucket to select
     * @returns {void}
     */
    base.selectBucket = function(bucket) {
        if (typeof bucket === 'object') {
            base.currentBucket(bucket.id);
        } else {
            base.currentBucket(bucket);
        }
        base.canUpload(true);
        base.isTrash(false);
        base.currentPage(1);
        base.objects.removeAll();
        base.listObjects();
    };

    // --------------------------------------------------------------------------

    /**
     * Watches the keyboard entry and creates a new bucket when ENTER is pressed
     * @param {object} thisClass A reference to this class
     * @param {object} event The event object
     * @return {boolean} Return true to allow the key press to go through
     */
    base.createBucket = function(thisClass, event) {
        if (event.which === base.keymap.ENTER) {
            $.ajax({
                    'url': window.SITE_URL + 'api/cdn/bucket',
                    'method': 'POST',
                    'data': {
                        'label': event.currentTarget.value
                    }
                })
                .done(function(response) {
                    base.showAddBucket(false);
                    base.listBuckets();
                    base.selectBucket(response.data.id);
                    base.success('Bucket created');
                })
                .fail(function(response) {
                    base.error('Failed to create bucket.', response);
                });
        } else if (event.which === base.keymap.ESC) {
            base.showAddBucket(false);
        }
        return true;
    };

    // --------------------------------------------------------------------------

    /**
     * Uploads new object(s)
     * @param {object} thisClass A reference to this class
     * @param {object} event The event object
     * @return {boolean} Return true to allow the DOM element to behave properly
     */
    base.uploadObject = function(thisClass, event) {

        $.each(event.currentTarget.files, function(index, file) {
            let element = base.addObject({'label': file.name, 'is_uploading': true}, true);
            let bucket = base.getBucketById(base.currentBucket());

            if (!bucket) {
                element.error('Unable to determine upload bucket.');
                return false;
            }

            //  Test file size
            if (bucket.max_size && file.size > bucket.max_size) {
                element.error('File is too big; maximum file size for this bucket is ' + bucket.max_size_human);
                return false;
            }

            // Uploading - for Firefox, Google Chrome and Safari
            let xhr = new XMLHttpRequest();

            // Update progress bar
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    let percent = Math.floor((e.loaded / e.total) * 100);
                    element.upload_progress(percent);
                }
            }, false);

            //  Error
            xhr.addEventListener('error', function(e) {
                //  @todo (Pablo - 2017-11-23) - more verbose errors
                element.error('An error occurred whilst uploading the file.');
            }, false);

            // File uploaded
            xhr.addEventListener('load', function(e) {
                if (e.currentTarget.readyState === 4) {
                    let data;
                    if (e.currentTarget.status === 200) {
                        try {
                            data = JSON.parse(e.currentTarget.responseText);
                        } catch (e) {
                            data = {'error': 'An unknown error occurred.'};
                        }

                        //  Update the object
                        element.id = data.object.id;
                        element.label = data.object.object.name;
                        element.ext = element.label.substr((element.label.lastIndexOf('.') + 1));
                        element.url = {
                            'src': data.object.url.src,
                            'preview': data.object.is_img ? data.object.url['400x400-crop'] : null
                        };
                        element.is_img = data.object.is_img;
                        element.is_uploading(false);

                    } else {
                        try {
                            data = JSON.parse(e.currentTarget.responseText);
                        } catch (e) {
                            data = {'error': 'An unknown error occurred.'};
                        }
                        element.error(data.error);
                    }
                } else {
                    //  @todo (Pablo - 2017-11-23) - Handle other readyState, errors?
                }
            }, false);

            xhr.open('post', window.SITE_URL + 'api/cdn/object/create', true);

            // Set appropriate headers
            xhr.setRequestHeader('X-cdn-bucket', bucket.slug);

            //  If the request is for an image then let's get a preview
            xhr.setRequestHeader('X-cdn-urls', '400x400-crop');

            // Send the file
            let formData = new FormData();
            formData.append('upload', file);
            xhr.send(formData);
        });

        event.currentTarget.value = null;
        base.droppable(false);

        return true;
    };

    // --------------------------------------------------------------------------

    /**
     * Deletes an object both from the interface and the CDN if an ID is present
     * @returns {void}
     */
    base.deleteObject = function() {
        let object = this;
        if (object.id) {

            let message = 'Are you sure?';
            if (base.isTrash()) {
                message += ' You cannot undo this action.';
            }

            if (confirm(message)) {
                $.ajax({
                        'url': window.SITE_URL + 'api/cdn/object/delete',
                        'method': 'POST',
                        'data': {
                            'object_id': object.id,
                            'is_trash': base.isTrash()
                        }
                    })
                    .done(function() {
                        base.objects.remove(object);
                        base.success('Item deleted');
                    })
                    .fail(function() {
                        base.error('Failed to delete object. It may be in use.');
                    });
            }

        } else {
            base.objects.remove(object);
        }
    };

    // --------------------------------------------------------------------------

    /**
     * Restores an item from the trash
     * @returns {void}
     */
    base.restoreObject = function() {
        let object = this;
        $.ajax({
                'url': window.SITE_URL + 'api/cdn/object/restore',
                'method': 'POST',
                'data': {
                    'object_id': object.id
                }
            })
            .done(function() {
                base.objects.remove(object);
                base.success('Item restored');
            })
            .fail(function() {
                base.error('Failed to restore object.');
            });
    };

    // --------------------------------------------------------------------------

    /**
     * Calls the callback
     * @returns {void}
     */
    base.executeCallback = function() {
        if (callbackHandler === 'ckeditor') {
            base.callbackCKEditor(this);
            window.close();
        } else {
            base.callbackPicker(this);
            window.parent.$.fancybox.close();
        }
    };

    // --------------------------------------------------------------------------

    /**
     * Executes the CKEditor callback
     * @param {object} object The selected object
     * @returns {void}
     */
    base.callbackCKEditor = function(object) {
        window.opener.CKEDITOR.tools.callFunction(callback[0], object.url.src);
    };

    // --------------------------------------------------------------------------

    /**
     * Executes the Picker callback
     * @param {object} object The selected object
     * @returns {void}
     */
    base.callbackPicker = function(object) {
        if (isModal) {
            window
                .parent[callback[0]][callback[1]]
                .call(null, object.id);
        } else {
            window
                .opener[callback[0]][callback[1]]
                .call(null, object.id);
        }
    };

    // --------------------------------------------------------------------------

    /**
     * Adds a new object to the list
     * @param {object} object The object details
     * @param {boolean} unshift Whether to use unshift, or push
     * @returns {object} the new object
     */
    base.addObject = function(object, unshift) {
        base.debug('Adding object');
        let newObject = {
            'id': object.id || null,
            'label': object.label || null,
            'ext': object.ext || null,
            'url': object.url || null,
            'is_img': object.is_img || null,
            'is_uploading': ko.observable(object.is_uploading || false),
            'upload_progress': ko.observable(0),
            'error': ko.observable()
        };
        if (unshift) {
            base.objects.unshift(newObject);
        } else {
            base.objects.push(newObject);
        }
        return newObject;
    };

    // --------------------------------------------------------------------------

    /**
     * Retrieves and stores the list of buckets from the server
     * @returns {promise} A promise, to be resolved when the request is complete
     */
    base.listBuckets = function() {
        base.debug('Listing buckets');
        let $deferred = new $.Deferred();
        $.ajax({
                'url': window.SITE_URL + 'api/cdn/bucket'
            })
            .done(function(response) {
                base.buckets.removeAll();
                $.each(response.data, function(index, bucket) {
                    base.addBucket(bucket);
                });
                $deferred.resolve();
            })
            .fail(function() {
                base.error('Failed to retrieve list of buckets from the server.');
                $deferred.reject();
            });
        return $deferred.promise();
    };

    // --------------------------------------------------------------------------

    /**
     * Fetches an individual bucket form the list by its ID
     * @param {number} id The bucket's ID
     * @returns {object}|null
     */
    base.getBucketById = function(id) {
        for (let i = 0, j = base.buckets().length; i < j; i++) {
            if (base.buckets()[i].id === id) {
                return base.buckets()[i];
            }
        }
        return null;
    };

    // --------------------------------------------------------------------------

    /**
     * Fetches an individual bucket form the list by its slug
     * @param {string} slug The bucket's slug
     * @returns {object}|null
     */
    base.getBucketBySlug = function(slug) {
        for (let i = 0, j = base.buckets().length; i < j; i++) {
            if (base.buckets()[i].slug === slug) {
                return base.buckets()[i];
            }
        }
        return null;
    };

    // --------------------------------------------------------------------------

    /**
     * List a page of objects in the current buckets
     * @returns {promise} A promise, to be resolved when the request is complete
     */
    base.listObjects = function() {
        base.debug('Listing objects');
        let $deferred = new $.Deferred();
        let url, data;

        if (base.isSearching()) {
            url = window.SITE_URL + 'api/cdn/object/search';
            data = {
                'keywords': base.searchTerm(),
                'page': base.currentPage()
            };
        } else if (base.isTrash()) {
            url = window.SITE_URL + 'api/cdn/object/trash';
            data = {
                'page': base.currentPage()
            };
        } else {
            url = window.SITE_URL + 'api/cdn/bucket/list';
            data = {
                'bucket_id': base.currentBucket(),
                'page': base.currentPage()
            };
        }

        $.ajax({
                'url': url,
                'data': data
            })
            .done(function(response) {
                $.each(response.data, function(index, object) {
                    base.addObject({
                        'id': object.id || null,
                        'label': object.file.name.human || null,
                        'size': object.file.size.human || null,
                        'ext': object.file.ext || null,
                        'url': object.url || null,
                        'is_img': object.is_img || false
                    });
                });
                base.currentPage(response.page + 1);
                base.showLoadMore(response.data.length >= response.per_page);
                $deferred.resolve();
            })
            .fail(function() {
                base.error('Failed to retrieve list of objects from the server.');
                $deferred.reject();
            });
        return $deferred.promise();
    };

    // --------------------------------------------------------------------------

    /**
     * Executes a search
     * @returns {boolean} Return true to allow the key press to go through
     */
    base.search = function() {
        clearTimeout(base.searchTimeout);
        base.searchTimeout = setTimeout(function() {
            let keywords = $.trim(base.searchTerm());
            if (keywords.length) {
                if (keywords !== base.lastSearch()) {
                    base.isSearching(true);
                    base.isTrash(false);
                    base.canUpload(false);
                    base.lastSearch(keywords);
                    base.objects.removeAll();
                    base.currentPage(1);
                    base.listObjects();
                }
            } else {
                base.stopSearch();
            }
        }, 150);

        return true;
    };

    // --------------------------------------------------------------------------

    /**
     * Stops searching and resets the previously loaded bucket
     * @returns {void}
     */
    base.stopSearch = function() {
        base.isSearching(false);
        base.canUpload(true);
        base.currentPage(1);
        base.objects.removeAll();
        base.listObjects();
    };

    // --------------------------------------------------------------------------

    /**
     * Lists items in the trash
     * @returns {void}
     */
    base.browseTrash = function() {
        base.canUpload(false);
        base.isTrash(true);
        base.currentPage(1);
        base.objects.removeAll();
        base.listObjects();
    };

    // --------------------------------------------------------------------------

    /**
     * Render a success message
     * @param {string} type The type of feedback
     * @param {string} message The message to render
     * @return {promise} A promise, resolved when the message is closed
     */
    base.feedback = function(type, message) {
        base.debug('Feedback: ' + type + ': ' + message);
        let $deferred = new $.Deferred();
        let $element = $('.manager__feedback__' + type);

        $element
            .html(message)
            .addClass('show');

        setTimeout(function() {
            base.debug('Closing feedback: ' + type);
            $deferred.resolve();
            $element.removeClass('show');
        }, 2500);

        return $deferred.promise();
    };

    // --------------------------------------------------------------------------

    /**
     * Render a success message
     * @param {string} message The message to render
     * @return {promise} A promise, resolved when the message is closed
     */
    base.success = function(message) {
        return base.feedback('success', message);
    };

    // --------------------------------------------------------------------------

    /**
     * Render an error message
     * @param {string} message The message to render
     * @return {promise} A promise, resolved when the message is closed
     */
    base.error = function(message) {
        return base.feedback('error', message || 'An unknown error occurred.');
    };

    // --------------------------------------------------------------------------

    /**
     * Render a debug message
     * @param {string} message The message to render
     * @return {promise} A promise, resolved when the message is closed
     */
    base.debug = function(message) {
        if (typeof console === 'object') {
            console.log('[Media Manager] ', message);
        }
        return base;
    };

    // --------------------------------------------------------------------------

    return base.__construct();
}

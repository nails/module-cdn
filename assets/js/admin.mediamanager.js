/* globals ko, $ */
function MediaManager(initialBucket, callbackHandler, callback, isModal) {
    let base = this;

    // --------------------------------------------------------------------------

    base.buckets = ko.observableArray();
    base.objects = ko.observableArray();
    base.currentBucket = ko.observable();
    base.currentPage = ko.observable(1);
    base.showLoadMore = ko.observable(false);
    base.showAddBucket = ko.observable(false);
    base.droppable = ko.observable(false);

    // --------------------------------------------------------------------------

    base.keymap = {
        'ENTER': 13,
        'ESC': 27
    };

    // --------------------------------------------------------------------------

    base.__construct = function() {
        base.debug('Constructing');
        base.bindEvents();
        base.init();
        return base;
    };

    // --------------------------------------------------------------------------

    base.bindEvents = function() {
        base.debug('Binding events');
    };

    // --------------------------------------------------------------------------

    base.init = function() {
        base.debug('Initialsiing');
        base.listBuckets()
            .done(function() {
                //  If bucket is defined, then set it as current, else take the first bucket in the list
                if (initialBucket) {
                    let bucket = base.getBucketBySlug(initialBucket);
                    base.currentBucket(bucket.id);
                } else {
                    base.currentBucket(base.buckets()[0].id);
                }
                base.listObjects();
            });
    };

    // --------------------------------------------------------------------------

    /**
     * Adds a new bucket to the list
     * @param {object} bucket The bucket details
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
                return bucket.id === base.currentBucket();
            })
        });
    };

    // --------------------------------------------------------------------------

    base.selectBucket = function(bucket) {
        if (typeof bucket === 'object') {
            base.currentBucket(bucket.id);
        } else {
            base.currentBucket(bucket);
        }
        base.currentPage(1);
        base.objects.removeAll();
        base.listObjects();
    };

    // --------------------------------------------------------------------------

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
     */
    base.deleteObject = function() {
        let object = this;
        if (object.id) {

            if (confirm('Are you sure?')) {
                $.ajax({
                        'url': window.SITE_URL + 'api/cdn/object/delete',
                        'method': 'POST',
                        'data': {
                            'object_id': object.id
                        }
                    })
                    .done(function() {
                        base.objects.remove(object);
                    })
                    .fail(function() {
                        this.error('Failed to delete object. It may be in use.');
                    });
            }

        } else {
            base.objects.remove(object);
        }
    };

    // --------------------------------------------------------------------------

    /**
     * Calls the callback
     */
    base.executeCallback = function() {
        if (callbackHandler === 'ckeditor') {
            base.callbackCKEditor(this);
        } else {
            base.callbackPicker(this);
        }

        if (isModal) {
            window.parent.$.fancybox.close();
        } else {
            window.close();
        }
    };

    // --------------------------------------------------------------------------

    base.callbackCKEditor = function(object) {
        window.opener.CKEDITOR.tools.callFunction(callback[0], object.url.serve);
    };

    // --------------------------------------------------------------------------

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
     */
    base.listObjects = function() {
        base.debug('Listing objects');
        let $deferred = new $.Deferred();
        $.ajax({
                'url': window.SITE_URL + 'api/cdn/bucket/list',
                'data': {
                    'bucket_id': base.currentBucket(),
                    'page': base.currentPage()
                }
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
     * @param {object} response The response, the `error` property it will be extracted and appended to the message
     * @return {promise} A promise, resolved when the message is closed
     */
    base.error = function(message, response) {
        console.log(response);
        return base.feedback('error', message || 'An unknown error occurred.');
    };

    // --------------------------------------------------------------------------

    /**
     * Render an info message
     * @param {string} message The message to render
     * @return {promise} A promise, resolved when the message is closed
     */
    base.info = function(message) {
        return base.feedback('info', message);
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

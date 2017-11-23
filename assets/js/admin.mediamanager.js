/* globals ko, $ */
function MediaManager($container) {
    let base = this;

    // --------------------------------------------------------------------------

    base.buckets = ko.observableArray();
    base.objects = ko.observableArray();
    base.currentBucket = ko.observable();
    base.currentPage = ko.observable(1);
    base.showLoadMore = ko.observable(false);

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
                if ($container.data('bucket-slug')) {
                    let bucket = base.getBucketBySlug($container.data('bucket-slug'));
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
     * @param {string} bucket The bucket details
     */
    base.addBucket = function(bucket) {
        base.debug('Adding bucket:' + bucket.label);
        base.buckets.push({
            'id': bucket.id || null,
            'slug': bucket.slug || null,
            'label': bucket.label || null,
            'is_uploading': ko.observable(bucket.is_uploading || false),
            'is_selected': ko.computed(function() {
                return bucket.id === base.currentBucket();
            })
        });
    };

    // --------------------------------------------------------------------------

    base.selectBucket = function() {
        base.currentBucket(this.id);
        base.currentPage(1);
        base.objects.removeAll();
        base.listObjects();
    };

    // --------------------------------------------------------------------------

    /**
     * Adds a new object to the list
     * @param {string} object The object details
     */
    base.addObject = function(object) {
        base.debug('Adding object');
        base.objects.push({
            'id': object.id || null,
            'label': object.file.name.human || null,
            'size': object.file.size.human || null,
            'ext': object.file.ext || null,
            'url': object.url || null,
            'is_img': object.is_img || false,
            'is_uploading': ko.observable(object.is_uploading || false)
        });
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
                $.each(response.data, function(index, bucket) {
                    if (!base.getBucketById(bucket.id)) {
                        base.addBucket(bucket);
                    }
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
                    base.addObject(object);
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
        let $element = $('.manager__feedback__' + type, $container);

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

// --------------------------------------------------------------------------

ko.applyBindings(
    new MediaManager(
        $('#module-cdn-manager')
    )
);

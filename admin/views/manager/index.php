<!-- ko if: !ready() -->
<div class="module-cdn manager--loading">
    <svg width="30" height="30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" class="lds-ring" style="background: none;">
        <circle cx="50" cy="50" ng-attr-r="{{config.radius}}" ng-attr-stroke="{{config.base}}" ng-attr-stroke-width="{{config.width}}" fill="none" r="30" stroke="#c5c5c5" stroke-width="10"></circle>
        <circle cx="50" cy="50" ng-attr-r="{{config.radius}}" ng-attr-stroke="{{config.stroke}}" ng-attr-stroke-width="{{config.innerWidth}}" ng-attr-stroke-linecap="{{config.linecap}}" fill="none" r="30" stroke="#000000" stroke-width="10" stroke-linecap="square" transform="rotate(168 50 50)">
            <animateTransform attributeName="transform" type="rotate" calcMode="linear" values="0 50 50;180 50 50;720 50 50" keyTimes="0;0.5;1" dur="1s" begin="0s" repeatCount="indefinite"></animateTransform>
            <animate attributeName="stroke-dasharray" calcMode="linear" values="18.84955592153876 169.64600329384882;94.2477796076938 94.24777960769377;18.84955592153876 169.64600329384882" keyTimes="0;0.5;1" dur="1" begin="0s" repeatCount="indefinite"></animate>
        </circle>
    </svg>
</div>
<!-- /ko -->
<div class="module-cdn manager hidden" data-bind="css: {hidden: !ready()}">
    <!-- ko if: !ready() -->
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="17" viewBox="0 0 24 17">
        <g fill="none" fill-rule="evenodd" stroke="#999999" transform="translate(1 1)">
            <path d="M11,14 L11,6"/>
            <polyline stroke-linecap="square" points="8 9 11 6 14 9"/>
            <path stroke-linecap="square" d="M16,15 L18,15 C20.209,15 22,13.207239 22,10.9985335 C22,8.80182642 20.218,6.98606852 17.975,7.00206639 C17.718,3.09358752 14.474,0 10.5,0 C6.481,0 3.21,3.16357819 3.018,7.13504866 C1.287,7.57399013 0,9.1297827 0,10.9985335 C0,13.207239 1.791,15 4,15 L6,15"/>
        </g>
    </svg>
    <!-- /ko -->
    <!-- ko if: ready() -->
    <div class="manager__browse">
        <div class="manager__browse__buckets">
            <ul class="manager__browse__buckets__list">
                <li class="manager__browse__buckets__list__search">
                    <input type="search" placeholder="Search for an object" data-bind="event: {keydown: $root.search, keyup: $root.search}, textInput: $root.searchTerm">
                </li>
                <!-- ko foreach: buckets -->
                <li class="manager__browse__buckets__list__item"
                    data-bind="click: $root.selectBucket, css: {selected: is_selected()}">
                    <span data-bind="html:label"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="8" height="12" viewBox="0 0 8 12">
                        <polygon fill="#444444" points="218 35.4 216.6 34 220.6 30 216.6 26 218 24.6 223.4 30" transform="translate(-216 -24)"/>
                    </svg>
                </li>
                <!-- /ko -->
                <!-- ko if: $root.showAddBucket() -->
                <li class="manager__browse__buckets__list__add">
                    <input type="text" id="add-bucket"
                           data-bind="
                            event: {
                                keydown: $root.createBucket,
                                blur: function() { $root.showAddBucket(false); }
                            }
                    ">
                </li>
                <!-- /ko -->
                <?php
                if (userHasPermission('admin:cdn:buckets:create')) {
                    ?>
                    <!-- ko if: !$root.showAddBucket() -->
                    <li class="manager__browse__buckets__list__action">
                        <button class="action" data-bind="click: function() { $root.showAddBucket(true); $('#add-bucket').val('').focus(); }">
                            Add bucket
                        </button>
                    </li>
                    <!-- /ko -->
                    <?php
                }
                ?>
            </ul>
        </div>
        <div class="manager__browse__objects">
            <!-- ko if: !buckets().length -->
            <div class="manager__browse__objects__empty">
                Create a bucket, so you can upload files
            </div>
            <!-- /ko -->
            <!-- ko if: buckets().length -->

            <!-- ko if: isSearching() -->
            <div class="manager__browse__objects__search">
                Searching objects for "<span data-bind="html: $root.searchTerm"></span>"
            </div>
            <!-- /ko -->
            <?php
            if (!userHasPermission('admin:cdn:objects:create')) {
                ?>
                <!-- ko if: !isSearching() -->
                <div class="manager__upload" data-bind="
                    css: {droppable: $root.droppable},
                    event: {
                        dragenter: function() { $root.droppable(true); return true; },
                        dragleave: function() { $root.droppable(false); return true; },
                        drop: function() { $root.droppable(false); return true; },
                    }
                ">
                    <input multiple type="file" data-bind="event: {change: $root.uploadObject}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="17" viewBox="0 0 24 17">
                        <g fill="none" fill-rule="evenodd" stroke="#999999" transform="translate(1 1)">
                            <path d="M11,14 L11,6"/>
                            <polyline stroke-linecap="square" points="8 9 11 6 14 9"/>
                            <path stroke-linecap="square" d="M16,15 L18,15 C20.209,15 22,13.207239 22,10.9985335 C22,8.80182642 20.218,6.98606852 17.975,7.00206639 C17.718,3.09358752 14.474,0 10.5,0 C6.481,0 3.21,3.16357819 3.018,7.13504866 C1.287,7.57399013 0,9.1297827 0,10.9985335 C0,13.207239 1.791,15 4,15 L6,15"/>
                        </g>
                    </svg>
                    <p>drag and drop your files here to upload</p>
                    <p>
                        <small>or click to browse</small>
                    </p>
                </div>
                <!-- /ko -->
                <?php
            }
            ?>
            <!-- ko if: objects().length -->
            <ul class="manager__browse__objects__list">
                <!-- ko foreach: objects -->
                <!-- ko if: error() -->
                <li class="manager__browse__objects__list__error">
                    <span data-bind="html: label"></span>:
                    <span data-bind="html: error"></span>
                    <button class="action action--delete" data-bind="click: $root.deleteObject">
                        Delete
                    </button>
                </li>
                <!-- /ko -->
                <!-- ko if: !error() -->
                <!-- ko if: is_uploading() -->
                <li class="manager__browse__objects__list__upload">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" class="lds-ring" style="background: none;">
                        <circle cx="50" cy="50" ng-attr-r="{{config.radius}}" ng-attr-stroke="{{config.base}}" ng-attr-stroke-width="{{config.width}}" fill="none" r="30" stroke="#c5c5c5" stroke-width="10"></circle>
                        <circle cx="50" cy="50" ng-attr-r="{{config.radius}}" ng-attr-stroke="{{config.stroke}}" ng-attr-stroke-width="{{config.innerWidth}}" ng-attr-stroke-linecap="{{config.linecap}}" fill="none" r="30" stroke="#000000" stroke-width="10" stroke-linecap="square" transform="rotate(168 50 50)">
                            <animateTransform attributeName="transform" type="rotate" calcMode="linear" values="0 50 50;180 50 50;720 50 50" keyTimes="0;0.5;1" dur="1s" begin="0s" repeatCount="indefinite"></animateTransform>
                            <animate attributeName="stroke-dasharray" calcMode="linear" values="18.84955592153876 169.64600329384882;94.2477796076938 94.24777960769377;18.84955592153876 169.64600329384882" keyTimes="0;0.5;1" dur="1" begin="0s" repeatCount="indefinite"></animate>
                        </circle>
                    </svg>
                    <div data-bind="html: upload_progress() + '%'"></div>
                </li>
                <!-- /ko -->
                <!-- ko if: !is_uploading() -->
                <!-- ko  if: is_img -->
                <li class="manager__browse__objects__list__item"
                    data-bind="style: { 'background-image': url.preview ? 'url(' + url.preview + ')' : '' }">

                    <div class="actions">
                        <button class="action action--delete" data-bind="click: $root.deleteObject">
                            delete
                        </button>
                        <a class="action action--view" target="_blank" data-bind="attr:{href: url.src}">
                            View
                        </a>
                        <!-- ko if: $root.showInsert() -->
                        <button class="action action--insert" data-bind="click: $root.executeCallback">
                            Insert
                        </button>
                        <!-- /ko -->
                    </div>
                </li>
                <!-- /ko -->
                <!-- ko  if: !is_img -->
                <li class="manager__browse__objects__list__item">
                    <div class="manager__browse__objects__list__item__ext" data-bind="html: ext"></div>
                    <div class="manager__browse__objects__list__item__label" data-bind="html: label"></div>
                    <div class="actions">
                        <button class="action action--delete" data-bind="click: $root.deleteObject">
                            delete
                        </button>
                        <a class="action action--view" target="_blank" data-bind="attr:{href: url.src}">
                            View
                        </a>
                        <!-- ko if: $root.showInsert() -->
                        <button class="action action--insert" data-bind="click: $root.executeCallback">
                            Insert
                        </button>
                        <!-- /ko -->
                    </div>
                </li>
                <!-- /ko -->
                <!-- /ko -->
                <!-- /ko -->
                <!-- /ko -->
            </ul>
            <!-- ko if: $root.showLoadMore -->
            <button class="manager__browse__objects__more" data-bind="click: $root.listObjects">
                Load More
            </button>
            <!-- /ko -->
            <!-- /ko -->
            <!-- ko if: !objects().length -->
            <div class="manager__browse__objects__empty">
                <!-- ko if: isSearching() -->
                No objects found
                <!-- /ko -->
                <!-- ko if: !isSearching() -->
                No objects in this bucket
                <!-- /ko -->
            </div>
            <!-- /ko -->
            <!-- /ko -->
        </div>
    </div>
    <div class="manager__feedback">
        <div class="manager__feedback__error"></div>
        <div class="manager__feedback__success"></div>
        <div class="manager__feedback__info"></div>
    </div>
    <!-- /ko -->
</div>

<div class="module-cdn manager" id="module-cdn-manager" data-bucket-slug="migration">
    <div class="manager__browse">
        <div class="manager__browse__buckets">
            <ul class="manager__browse__buckets__list">
                <!-- ko foreach: buckets -->
                <li class="manager__browse__buckets__list__item"
                    data-bind="click: $root.selectBucket, css: {selected: is_selected()}">
                    <span data-bind="html:label"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="8" height="12" viewBox="0 0 8 12">
                        <polygon fill="#444444" points="218 35.4 216.6 34 220.6 30 216.6 26 218 24.6 223.4 30" transform="translate(-216 -24)"/>
                    </svg>
                </li>
                <!-- /ko -->
                <li class="manager__browse__buckets__list__action">
                    <button class="action">Add bucket</button>
                </li>
            </ul>
        </div>
        <div class="manager__browse__objects">
            <div class="manager__upload">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="17" viewBox="0 0 24 17">
                    <g fill="none" fill-rule="evenodd" stroke="#999999" transform="translate(1 1)">
                        <path d="M11,14 L11,6"/>
                        <polyline stroke-linecap="square" points="8 9 11 6 14 9"/>
                        <path stroke-linecap="square" d="M16,15 L18,15 C20.209,15 22,13.207239 22,10.9985335 C22,8.80182642 20.218,6.98606852 17.975,7.00206639 C17.718,3.09358752 14.474,0 10.5,0 C6.481,0 3.21,3.16357819 3.018,7.13504866 C1.287,7.57399013 0,9.1297827 0,10.9985335 C0,13.207239 1.791,15 4,15 L6,15"/>
                    </g>
                </svg>
                <p>drag and drop your files here</p>
                <p>
                    <small>or browse</small>
                </p>
            </div>
            <!-- ko if: objects().length -->
            <ul class="manager__browse__objects__list">
                <!-- ko foreach: objects -->
                <!-- ko  if: is_img -->
                <li class="manager__browse__objects__list__item"
                    data-bind="style: { 'background-image': url.preview ? 'url(' + url.preview + ')' : '' }">
                    <div class="actions">
                        <button class="action action--delete">delete</button>
                        <button class="action action--view">View</button>
                        <button class="action action--insert">Insert</button>
                    </div>
                </li>
                <!-- /ko -->
                <!-- ko  if: !is_img -->
                <li class="manager__browse__objects__list__item">
                    <div class="manager__browse__objects__list__item__ext" data-bind="html: ext"></div>
                    <div class="manager__browse__objects__list__item__label" data-bind="html: label"></div>
                    <div class="actions">
                        <button class="action action--delete">delete</button>
                        <button class="action action--view">View</button>
                        <button class="action action--insert">Insert</button>
                    </div>
                </li>
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
            <div class="manager__browse__objects__empty">No objects in this bucket</div>
            <!-- /ko -->
        </div>
    </div>
    <div class="manager__feedback">
        <div class="manager__feedback__error"></div>
        <div class="manager__feedback__success"></div>
        <div class="manager__feedback__info"></div>
    </div>
</div>

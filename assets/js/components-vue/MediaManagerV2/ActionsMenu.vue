<template>
    <!-- This component doesn't render anything in its slot, it just manages the menu in the document body -->
</template>

<script>
// Global event bus for menu coordination
const menuEventBus = {
    events: {},
    $on(eventName, fn) {
        this.events[eventName] = this.events[eventName] || [];
        this.events[eventName].push(fn);
    },
    $off(eventName) {
        if (this.events[eventName]) {
            delete this.events[eventName];
        }
    },
    $emit(eventName, data) {
        if (this.events[eventName]) {
            this.events[eventName].forEach(fn => fn(data));
        }
    }
};

export default {
    name: 'ActionsMenu',
    inject: ['userPermissions'],
    props: {
        isOpen: {
            type: Boolean,
            required: true
        },
        theme: {
            type: String,
            default: 'light',
            validator: value => ['light', 'dark'].includes(value)
        },
        hasCallback: {
            type: Boolean,
            default: false
        },
        triggerElement: {
            type: [Object, HTMLElement],
            default: null
        },
    },
    data() {
        return {
            openUpwards: false,
            menuPosition: {top: 0, left: 0},
            menuElement: null
        }
    },
    created() {
        // Listen for other menus being opened
        menuEventBus.$on('menu-opened', (menuId) => {
            // If this isn't the menu that was just opened and our menu is open, close it
            if (menuId !== this._uid && this.isOpen) {
                this.$emit('close');
            }
        });
    },
    watch: {
        isOpen(newValue) {
            if (newValue) {
                // Notify other menus that this one is opening
                menuEventBus.$emit('menu-opened', this._uid);
                this.createMenu();
            } else {
                this.removeMenu();
            }
        }
    },
    mounted() {
        document.addEventListener('click', this.handleClickOutside);
        document.addEventListener('keydown', this.handleKeydown);
        window.addEventListener('resize', this.handleResize);
        window.addEventListener('scroll', this.handleScroll, true);
    },
    beforeDestroy() {
        // Clean up event listeners
        menuEventBus.$off('menu-opened');
        document.removeEventListener('click', this.handleClickOutside);
        document.removeEventListener('keydown', this.handleKeydown);
        window.removeEventListener('resize', this.handleResize);
        window.removeEventListener('scroll', this.handleScroll, true);
        this.removeMenu();
    },
    methods: {
        createMenu() {
            // Calculate position before creating the menu
            this.calculateMenuPosition();

            // Create menu element
            this.menuElement = document.createElement('div');
            this.menuElement.className = `actions-menu actions-menu--${this.theme}`;
            this.menuElement.style.position = 'absolute';
            this.menuElement.style.zIndex = '9999';
            this.menuElement.style.top = `${this.menuPosition.top}px`;
            this.menuElement.style.left = `${this.menuPosition.left}px`;

            // Create menu content
            this.menuElement.innerHTML = `
                <div class="actions-menu__header">
                    <span>More actions</span>
                    <button class="close-button">&times;</button>
                </div>
                <div class="actions-menu__items">
                    ${this.hasCallback ? `
                    <button class="action-item" data-action="insert">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        <span>Insert</span>
                    </button>
                    ` : ''}
                    ${this.userPermissions.object.edit ? `
                    <button class="action-item" data-action="edit">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                        <span>Edit</span>
                    </button>
                    ` : ''}
                    ${this.userPermissions.object.replace ? `
                    <button class="action-item" data-action="replace">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                        </svg>
                        <span>Replace</span>
                    </button>
                    ` : ''}
                    ${this.userPermissions.object.move ? `
                    <button class="action-item" data-action="move">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                        </svg>
                        <span>Move</span>
                    </button>
                    ` : ''}
                    ${this.userPermissions.object.copy ? `
                    <button class="action-item" data-action="copy">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                        </svg>
                        <span>Copy</span>
                    </button>
                    ` : ''}
                    <button class="action-item" data-action="download">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        <span>Download</span>
                    </button>
                    <button class="action-item" data-action="copy-url">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                            <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                        </svg>
                        <span>Copy URL</span>
                    </button>
                    ${this.userPermissions.object.delete ? `
                    <button class="action-item action-item--danger" data-action="delete">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>Delete</span>
                    </button>
                    ` : ''}
                </div>
            `;

            // Add event listeners to menu items
            this.menuElement.addEventListener('click', this.handleMenuClick);

            // Append to body
            document.body.appendChild(this.menuElement);
        },

        removeMenu() {
            if (this.menuElement && this.menuElement.parentNode) {
                this.menuElement.removeEventListener('click', this.handleMenuClick);
                this.menuElement.parentNode.removeChild(this.menuElement);
                this.menuElement = null;
            }
        },

        calculateMenuPosition() {
            // Check if triggerElement is available
            if (!this.triggerElement) {
                // Default position in the center of the viewport if no trigger element
                this.menuPosition = {
                    top: window.innerHeight / 2 - 125, // Half of menu height
                    left: window.innerWidth / 2 - 100  // Half of menu width
                };
                return;
            }

            // Get the position of the dropdown button
            const buttonRect = this.triggerElement.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            const viewportWidth = window.innerWidth;
            const scrollY = window.scrollY || window.pageYOffset;
            const scrollX = window.scrollX || window.pageXOffset;

            // Calculate if there's enough space below (250px is approximate menu height)
            const spaceBelow = viewportHeight - buttonRect.bottom;
            this.openUpwards = spaceBelow < 250; // Open upwards if less than 250px below

            // Calculate the absolute position (including scroll)
            const menuWidth = 200; // Width of the menu in pixels
            const absoluteTop = buttonRect.top + scrollY;
            const absoluteLeft = buttonRect.left + scrollX;
            const absoluteRight = buttonRect.right + scrollX;

            // Position the menu
            if (this.openUpwards) {
                // For upward menus, position above the button with enough space
                // Ensure the menu doesn't go off the top of the viewport
                const menuHeight = 250; // Approximate menu height
                this.menuPosition = {
                    top: Math.max(scrollY + 10, absoluteTop - menuHeight),
                    left: Math.min(absoluteRight - menuWidth, viewportWidth + scrollX - menuWidth - 10)
                };
            } else {
                // For downward menus, ensure we're below the button
                this.menuPosition = {
                    top: absoluteTop + buttonRect.height + 4,
                    left: Math.min(absoluteRight - menuWidth, viewportWidth + scrollX - menuWidth - 10)
                };
            }
        },

        handleMenuClick(event) {
            // Close button
            if (event.target.classList.contains('close-button')) {
                this.$emit('close');
                return;
            }

            // Find the action button that was clicked
            let actionButton = event.target;
            while (actionButton && !actionButton.dataset.action) {
                actionButton = actionButton.parentElement;
            }

            if (actionButton && actionButton.dataset.action) {
                this.$emit('action', actionButton.dataset.action);
            }
        },

        handleClickOutside(event) {
            // Check if click is outside both the dropdown button and the menu
            if (!this.isOpen) {
                return;
            }

            // If there's no triggerElement, only check if click is outside the menu
            if (!this.triggerElement) {
                if (this.menuElement && !this.menuElement.contains(event.target)) {
                    this.$emit('close');
                }
                return;
            }

            // Check if click is outside both the dropdown button and the menu
            if (!this.triggerElement.contains(event.target) &&
                this.menuElement &&
                !this.menuElement.contains(event.target)) {
                this.$emit('close');
            }
        },

        handleKeydown(event) {
            if (event.key === 'Escape' && this.isOpen) {
                this.$emit('close');
            }
        },

        handleResize() {
            if (this.isOpen) {
                this.calculateMenuPosition();
                if (this.menuElement) {
                    this.menuElement.style.top = `${this.menuPosition.top}px`;
                    this.menuElement.style.left = `${this.menuPosition.left}px`;
                }
            }
        },

        handleScroll() {
            if (this.isOpen) {
                this.calculateMenuPosition();
                if (this.menuElement) {
                    this.menuElement.style.top = `${this.menuPosition.top}px`;
                    this.menuElement.style.left = `${this.menuPosition.left}px`;
                }
            }
        }
    }
}
</script>

<style lang="scss">
/* 
 * Note: We're removing 'scoped' from the style tag because we need these styles
 * to apply to the dynamically created menu that's appended to the document body
 */

/* Global styles for the menu that will be appended to the body */
.actions-menu {
    width: 200px;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    z-index: 9999; /* Extremely high z-index to ensure it's above all other elements */
    overflow: hidden;
    transform: none; /* Ensure no transforms are inherited */
    isolation: isolate; /* Create a new stacking context */
    will-change: transform; /* Force a new stacking context */
    pointer-events: auto; /* Ensure the menu can receive mouse events */

    /* Light theme styles */
    &.actions-menu--light {
        background: white;
        border: 1px solid #e5e7eb;

        .actions-menu__header {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;

            span {
                color: #4b5563;
            }

            .close-button {
                color: #6b7280;

                &:hover {
                    background-color: #e5e7eb;
                    color: #4b5563;
                }
            }
        }

        .action-item {
            color: #4b5563;

            svg {
                color: #6b7280;
            }

            &:hover {
                background-color: #f3f4f6;
                color: #4f46e5;

                svg {
                    color: #4f46e5;
                }
            }

            &--danger {
                color: #dc2626;

                svg {
                    color: #dc2626;
                }

                &:hover {
                    background-color: #fee2e2;
                    color: #b91c1c;

                    svg {
                        color: #b91c1c;
                    }
                }
            }
        }
    }

    /* Dark theme styles */
    &.actions-menu--dark {
        background: #1f2937;
        border: 1px solid rgba(255, 255, 255, 0.1);

        .actions-menu__header {
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);

            span {
                color: rgba(255, 255, 255, 0.8);
            }

            .close-button {
                color: rgba(255, 255, 255, 0.6);

                &:hover {
                    background: rgba(255, 255, 255, 0.1);
                    color: white;
                }
            }
        }

        .action-item {
            color: rgba(255, 255, 255, 0.8);

            svg {
                color: rgba(255, 255, 255, 0.6);
            }

            &:hover {
                background: rgba(255, 255, 255, 0.1);
                color: white;

                svg {
                    color: white;
                }
            }

            &--danger {
                color: #ef4444;

                svg {
                    color: #ef4444;
                }

                &:hover {
                    background: rgba(239, 68, 68, 0.1);
                    color: #f87171;

                    svg {
                        color: #f87171;
                    }
                }
            }
        }
    }

    &__header {
        padding: 8px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;

        span {
            font-size: 13px;
            font-weight: 600;
        }

        .close-button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 16px;
            line-height: 1;
        }
    }

    &__items {
        padding: 4px;
    }

    .action-item {
        width: 100%;
        padding: 8px 12px;
        border: none;
        background: none;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s ease;

        svg {
            width: 16px;
            height: 16px;
        }
    }
}
</style>

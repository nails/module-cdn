<template>
    <div class="grid-item" :class="{ 'is-image': item.is_img }">
        <div class="grid-item__content">
            <!-- Thumbnail/Icon -->
            <div class="thumbnail-container">
                <img v-if="item.is_img"
                     :src="item.url.thumb.grid"
                     :alt="item.file.name.human"
                     class="thumbnail">
                <div v-else class="file-icon">
                    <span class="file-icon__symbol">{{ item.file.ext.toUpperCase() }}</span>
                </div>
            </div>

            <!-- Hover Overlay -->
            <div class="hover-overlay">
                <div class="item-details">
                    <h3 class="filename" :title="item.file.name.human">{{ item.file.name.human }}</h3>
                    <div class="metadata">
                        <span class="size">{{ item.file.size.human }}</span>
                        <span class="separator">•</span>
                        <span class="date">{{ item.created.formatted }}</span>
                    </div>
                    <div class="bucket-tag">{{ item.bucket.label }}</div>
                    <object-actions 
                        :has-callback="hasCallback"
                        theme="dark"
                        @action="handleAction"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import ObjectActions from './ObjectActions.vue'

export default {
    name: 'ObjectGridItem',
    components: {
        ObjectActions
    },
    props: {
        item: {
            type: Object,
            required: true
        },
        hasCallback: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            isOpen: false
        }
    },
    mounted() {
        document.addEventListener('click', this.handleClickOutside);
        document.addEventListener('keydown', this.handleKeydown);
    },
    beforeDestroy() {
        document.removeEventListener('click', this.handleClickOutside);
        document.removeEventListener('keydown', this.handleKeydown);
    },
    methods: {
        handleAction(action) {
            this.$emit('action', {action, item: this.item});
        },
        handleClickOutside(event) {
            const dropdown = this.$el.querySelector('.actions-dropdown');
            if (dropdown && !dropdown.contains(event.target)) {
                this.isOpen = false;
            }
        },
        handleKeydown(event) {
            if (event.key === 'Escape' && this.isOpen) {
                this.isOpen = false;
            }
        }
    }
}
</script>

<style lang="scss" scoped>
.grid-item {
    position: relative;
    aspect-ratio: 1;
    background: #f5f5f5;
    border-radius: 8px;
    overflow: visible;
    transition: transform 0.2s ease;
    z-index: 1;

    &:hover {
        transform: scale(1.02);
        z-index: 2;

        .hover-overlay {
            opacity: 1;
        }
    }
}

.grid-item__content {
    position: relative;
    width: 100%;
    height: 100%;
}

.thumbnail-container {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.thumbnail {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.file-icon {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eeeeee;

    &__symbol {
        font-size: 40px;
        color: #666666;
    }
}

.hover-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.75);
    opacity: 0;
    transition: opacity 0.2s ease;
    padding: 15px;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}

.item-details {
    color: white;

    .filename {
        margin: 0 0 8px 0;
        font-size: 14px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .metadata {
        font-size: 12px;
        margin-bottom: 8px;

        .separator {
            margin: 0 5px;
        }
    }

    .bucket-tag {
        display: inline-block;
        background: rgba(255, 255, 255, 0.2);
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        margin-bottom: 8px;
    }
}

// Responsive adjustments
@media (max-width: 768px) {
    .item-details {
        .filename {
            font-size: 12px;
        }

        .metadata {
            font-size: 11px;
        }
    }
}
</style> 

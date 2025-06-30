<template>
    <tr>
        <td class="id-cell">{{ item.id }}</td>
        <td class="thumbnail-cell">
            <img
                v-if="item.is_img"
                :src="item.url.thumb.list"
                :alt="item.file.name.human"
                class="thumbnail"
            />
            <div v-else class="file-icon">
                <span>{{ item.file.ext.toUpperCase() }}</span>
            </div>
        </td>
        <td class="file-name-cell">
            {{ item.file.name.human }}
            <br><span class="bucket">{{ item.bucket.label }}</span>
        </td>
        <td class="file-type-cell">
            <span :title="item.file.mime">{{ item.group }}</span>
        </td>
        <td class="file-size-cell">{{ item.file.size.human }}</td>
        <td class="created-cell">{{ item.created_by?.name || 'Unknown' }}</td>
        <td class="uploader-cell">{{ item.created.formatted }}</td>
        <td class="actions-cell">
            <object-actions
                :has-callback="hasCallback"
                theme="light"
                @action="handleAction"
            />
        </td>
    </tr>
</template>

<script>
import ObjectActions from './ObjectActions.vue'

export default {
    name: 'ObjectListItem',
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
.thumbnail {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
}

.file-icon {
    width: 60px;
    height: 60px;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    color: #666666;
}

.bucket {
    background: #f0f1f4;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    margin-top: 0.35rem;
    display: inline-block;
}

.action-select {
    padding: 6px;
    border: 1px solid #dddddd;
    border-radius: 4px;
    background: #ffffff;
    width: 100%;

    &:focus {
        outline: none;
        border-color: #1a73e8;
    }
}

// Responsive adjustments
@media (max-width: 768px) {
    .thumbnail {
        width: 40px;
        height: 40px;
    }

    .file-icon {
        width: 40px;
        height: 40px;
    }
}

.actions-cell {
    width: 200px;
    padding: 0 16px;
    text-align: right;
    position: relative;
    z-index: 1001; /* Higher than the actions-menu z-index */
}
</style> 

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
            <select @change="handleAction($event)" class="action-select">
                <option value="">Select action...</option>
                <option v-if="hasCallback" value="insert">Insert</option>
                <option value="edit">Edit</option>
                <option value="delete">Delete</option>
                <option value="download">Download</option>
                <option value="copy-url">Copy URL</option>
            </select>
        </td>
    </tr>
</template>

<script>
export default {
    name: 'ObjectListItem',
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
    methods: {
        handleAction(event) {
            const action = event.target.value;
            if (!action) return;

            this.$emit('action', {action, item: this.item});

            // Reset select
            event.target.value = '';
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
</style> 

<template>
    <ModalBase
        :visible="visible"
        title="Insert Image"
        :locked="isLoading"
        @close="handleClose"
    >
        <div class="image-scaler">
            <div class="image-scaler__field">
                <label class="image-scaler__label">Scaling</label>
                <select class="image-scaler__select" v-model="scaling">
                    <option value="NONE">None (native resolution)</option>
                    <option value="CROP">Crop</option>
                    <option value="SCALE">Scale</option>
                </select>
                <p v-if="scaling === 'NONE'" class="image-scaler__hint">
                    The image will be embedded at its native
                    resolution<span v-if="metaSize"> &mdash; {{ metaSize }}</span>.
                </p>
            </div>

            <div v-if="scaling !== 'NONE'" class="image-scaler__field">
                <label class="image-scaler__label">Size</label>
                <select class="image-scaler__select" v-model="size">
                    <option
                        v-for="(dim, index) in permittedDimensions"
                        :key="index"
                        :value="`${dim.width}x${dim.height}`"
                    >{{ dim.width }} &times; {{ dim.height }}</option>
                </select>
            </div>

            <div v-if="scaling !== 'NONE'" class="image-scaler__preview">
                <p class="image-scaler__preview__label">Preview</p>
                <p v-if="previewLoading" class="image-scaler__preview__loading">Loading preview&hellip;</p>
                <img
                    v-if="previewUrl && !previewLoading"
                    class="image-scaler__preview__img"
                    :src="previewUrl"
                    @load="onPreviewLoad"
                />
                <p v-if="metaWidth && metaHeight" class="image-scaler__meta">
                    {{ metaWidth }} &times; {{ metaHeight }}<span v-if="metaSize"> &mdash; {{ metaSize }}</span>
                </p>
            </div>
        </div>

        <template #footer>
            <div class="safari-repaint-fix">
                <Button
                    variant="secondary"
                    text="Cancel"
                    icon="cancel"
                    @click="handleClose"
                    :disabled="isLoading"
                />
                <Button
                    variant="primary"
                    text="Insert"
                    @click="handleConfirm"
                    :disabled="isLoading"
                    :loading="isLoading"
                />
            </div>
        </template>
    </ModalBase>
</template>

<script>
import axios from 'axios';
import ModalBase from './ModalBase.vue';
import Button from '../Button.vue';

export default {
    name: 'ModalImageScaler',
    components: { ModalBase, Button },
    inject: ['cdnApi'],
    props: {
        visible: { type: Boolean, default: false },
        object: { type: Object, default: null },
        permittedDimensions: { type: Array, default: () => [] },
    },
    data() {
        return {
            scaling: 'NONE',
            size: '',
            previewUrl: null,
            previewLoading: false,
            metaWidth: null,
            metaHeight: null,
            metaSize: null,
            isLoading: false,
            urlCache: {},
        };
    },
    watch: {
        visible(newVal) {
            if (newVal) {
                this.reset();
            }
        },
        scaling() {
            this.fetchPreview();
        },
        size() {
            if (this.scaling !== 'NONE') {
                this.fetchPreview();
            }
        },
    },
    methods: {
        reset() {
            this.scaling = 'NONE';
            this.size = this.permittedDimensions.length
                ? `${this.permittedDimensions[0].width}x${this.permittedDimensions[0].height}`
                : '';
            this.previewUrl = null;
            this.previewLoading = false;
            this.metaWidth = null;
            this.metaHeight = null;
            this.metaSize = null;
            this.isLoading = false;
            this.urlCache = {};
            this.fetchPreview();
        },

        handleClose() {
            this.$emit('close');
        },

        async handleConfirm() {
            if (!this.object) return;

            if (this.scaling === 'NONE') {
                this.$emit('confirm', this.object.url.src);
                return;
            }

            const key = `${this.size}-${this.scaling.toLowerCase()}`;

            if (this.urlCache[key]) {
                this.$emit('confirm', this.urlCache[key]);
                return;
            }

            this.isLoading = true;
            try {
                const url = this.cdnApi.object.fetch(this.object.id) + '&urls=' + key;
                const response = await axios.get(url);
                const scaledUrl = response.data.data.url[key];
                this.urlCache[key] = scaledUrl;
                this.$emit('confirm', scaledUrl);
            } catch (e) {
                console.error('Failed to fetch scaled URL:', e);
            } finally {
                this.isLoading = false;
            }
        },

        async fetchPreview() {
            if (!this.object) return;

            this.metaWidth = null;
            this.metaHeight = null;
            this.metaSize = null;

            if (this.scaling === 'NONE') {
                this.previewUrl = null;
                if (this.object.object && this.object.object.size) {
                    this.metaSize = this.object.object.size.human;
                }
                return;
            }

            const key = `${this.size}-${this.scaling.toLowerCase()}`;

            if (this.urlCache[key]) {
                this.previewUrl = this.urlCache[key];
                return;
            }

            this.previewLoading = true;
            this.previewUrl = null;
            try {
                const url = this.cdnApi.object.fetch(this.object.id) + '&urls=' + key;
                const response = await axios.get(url);
                const scaledUrl = response.data.data.url[key];
                this.urlCache[key] = scaledUrl;
                this.previewUrl = scaledUrl;
            } catch (e) {
                console.error('Failed to fetch preview:', e);
            } finally {
                this.previewLoading = false;
            }
        },

        onPreviewLoad(event) {
            const img = event.target;
            img.decode().then(() => {
                this.metaWidth = img.naturalWidth;
                this.metaHeight = img.naturalHeight;
                const entries = window.performance
                    ? window.performance.getEntriesByName(img.src)
                    : [];
                const entry = entries[entries.length - 1];
                if (entry && entry.encodedBodySize > 0) {
                    this.metaSize = this.formatBytes(entry.encodedBodySize);
                }
            });
        },

        formatBytes(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },
    },
};
</script>

<style lang="scss" scoped>
.image-scaler {
    &__field {
        margin-bottom: 1rem;
    }

    &__label {
        display: block;
        margin-bottom: 0.3rem;
        font-size: 13px;
        font-weight: 600;
        color: #444444;
    }

    &__select {
        display: block;
        width: 100%;
        padding: 0.4rem 0.5rem;
        border: 1px solid #cacaca;
        border-radius: 2px;
        background-color: #fcfcfc;
        box-sizing: border-box;
    }

    &__hint {
        margin: 0.35rem 0 0;
        font-size: 12px;
        color: #999999;
    }

    &__preview {
        margin-bottom: 1rem;
        border: 1px solid #e0e0e0;
        border-radius: 2px;
        padding: 0.75rem;

        &__label {
            margin: 0 0 0.5rem;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #aaaaaa;
        }

        &__img {
            display: block;
            max-width: 100%;
            max-height: 350px;
            border: 1px solid #e0e0e0;
        }

        &__loading {
            color: #999999;
            font-size: 13px;
            padding: 2rem 0;
            text-align: center;
        }
    }

    &__meta {
        margin: 0.5rem 0 0;
        font-size: 12px;
        color: #999999;
    }
}

.safari-repaint-fix {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
}
</style>

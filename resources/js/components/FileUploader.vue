<template>
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">File list</div>
                <div class="card-body">
                    <div class="list-group">
                        <a v-for="(file, index) in files" :key="`file-${index}`" :href="file.url" class="list-group-item list-group-item-action" target="_blank">{{ file.file_name }}</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">File uploader</div>
                <div class="card-body">
                    <div id="dropzone" class="dropzone"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import Dropzone from "dropzone";

    export default {
        props: {
            chunkSize: {
                type: Number,
                required: true,
            }
        },
        data() {
            return {
                files: [],
                dropzoneInstance: null,
            }
        },
        created() {
            this.loadFiles()
        },
        mounted() {
            this.dropzoneInstance = new Dropzone("div#dropzone", {
                url: "/api/upload",
                chunkSize: this.chunkSize,
                chunking: true,
                forceChunking: true,
                retryChunks: true,
                retryChunksLimit: 5,
                headers: window.axios.defaults.headers.common,
            });
            this.dropzoneInstance.on("complete", file => {
                this.dropzoneInstance.removeFile(file)
                this.loadFiles()
            });
        },
        methods: {
            loadFiles() {
                axios.get('/api/files').then(response => {
                    this.files = response.data
                })
            }
        },
    }
</script>

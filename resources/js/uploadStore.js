import { defineStore } from 'pinia';

export const useUploadStore = defineStore('upload', {
    state: () => ({
        progress: 0,
        totalFileSize: 0,
        timeStarted: 0,
    }),
    actions: {
        reset() {
            this.progress = 0;
            this.totalFileSize = 0;
            this.timeStarted = 0;
        }
    }
});

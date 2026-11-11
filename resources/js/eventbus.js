import { reactive } from 'vue';

const EventBus = reactive({
    events: {},
    emit(eventName, payload) {
        if (!this.events[eventName]) {
            return;
        }
        this.events[eventName].forEach(callback => {
            callback(payload);
        });
    },
    on(eventName, callback) {
        if (!this.events[eventName]) {
            this.events[eventName] = [];
        }
        this.events[eventName].push(callback);
    },
    remove(eventName, callback) {
        if (!this.events[eventName]) {
            return;
        }
        this.events[eventName] = this.events[eventName].filter(
            eventCallback => eventCallback !== callback
        );
    },
    off(eventName, callback) {
        if (!this.events[eventName]) {
            return;
        }
        this.events[eventName] = this.events[eventName].filter(
            eventCallback => eventCallback !== callback
        );
    },
});

export default EventBus;

import { createApp, h } from "vue";
import { createPinia } from "pinia";
import piniaPluginPersistedstate from "pinia-plugin-persistedstate";
import EventBus from "./eventbus";
import PrimeVue from 'primevue/config'
import router from './router';
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import '../css/app.css'
import '../scss/app.scss';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            retry: (failureCount, error) => {
                const status = error?.response?.status;
                if (status === 403 || status === 429) return false;
                return failureCount < 1;
            },
            refetchOnWindowFocus: false,
            staleTime: 1000 * 60,
        },
    },
})
const store = createPinia();

store.use(piniaPluginPersistedstate);

import App from './App.vue';

const app = createApp(App)

app.use(store);
app.use(VueQueryPlugin, {
        queryClient,
        enableDevtoolsV6Plugin: true,
    });
app.use(router);
app.use(PrimeVue, {
    ripple: true
});
app.use(ConfirmationService)
app.use(ToastService)
app.mount('#app');
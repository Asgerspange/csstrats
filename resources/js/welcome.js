import { createApp } from 'vue';
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query';
import Welcome from './Welcome/Welcome.vue';
import '../css/welcome.css';
import '../css/app.css'
import '../scss/app.scss';


const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            retry: 1,
            refetchOnWindowFocus: false,
            staleTime: 1000 * 60 * 5,
        },
    },
});

createApp(Welcome)
    .use(VueQueryPlugin, { queryClient })
    .mount('#welcome-app');

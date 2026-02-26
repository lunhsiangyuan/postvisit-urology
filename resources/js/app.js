import './bootstrap';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from '@/router';
import App from '@/App.vue';
import i18n from '@/i18n';
import { useToastStore } from '@/stores/toast';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);
app.use(i18n);

app.config.errorHandler = (err) => {
    console.error('[PostVisit] Unhandled error:', err);
    const toast = useToastStore();
    toast.error('An unexpected error occurred. Please try again.');
};

app.mount('#app');

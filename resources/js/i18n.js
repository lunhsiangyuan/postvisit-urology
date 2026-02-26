import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';
import zhTW from '@/locales/zh-TW.json';

const i18n = createI18n({
    legacy: false,
    locale: localStorage.getItem('locale') || 'zh-TW',
    fallbackLocale: 'en',
    messages: { en, 'zh-TW': zhTW },
});

export default i18n;

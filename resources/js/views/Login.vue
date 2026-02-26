<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-[#f4f8f3] via-white to-[#f4f8f3]/80 px-4 relative overflow-hidden">
    <!-- Subtle decorative circles -->
    <div class="absolute -top-32 -right-32 w-80 h-80 bg-[#eef3ec]/60 rounded-full blur-3xl" />
    <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-[#eef3ec]/50 rounded-full blur-3xl" />

    <div class="w-full max-w-sm relative z-10">
      <div class="text-center mb-8">
        <router-link to="/" class="inline-block"><span class="font-bold text-2xl leading-none tracking-tight"><span class="text-[#4A6741]">PostVisit</span> <span class="text-[#1A365D]">Urology</span></span></router-link>
        <p class="mt-3 text-sm text-gray-400">{{ $t('app.tagline') }}</p>
      </div>

      <!-- Demo Access — prominent, above login form -->
      <div
        v-if="demoLoginEnabled"
        :class="[
          'login-demo-section mb-6 space-y-3 rounded-2xl border-2 border-[#c4d8bf] bg-[#f4f8f3]/50 p-5 shadow-lg transition-all duration-700 ease-out',
          demoVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'
        ]"
      >
        <p class="text-center text-sm font-semibold text-[#4A6741] uppercase tracking-wide">{{ $t('demo.tryDemo') }}</p>
        <p class="text-center text-xs text-[#4A6741]">{{ $t('demo.noAccountNeeded') }}</p>
        <div class="flex gap-2">
          <button
            :disabled="loading"
            class="flex-1 py-2.5 bg-[#4A6741] text-white rounded-lg font-medium hover:bg-[#3d5636] transition-colors disabled:opacity-50 text-sm"
            @click="router.push('/demo/scenarios')"
          >
            {{ $t('demo.signInAsPatient') }}
          </button>
          <button
            :disabled="loading"
            class="flex-1 py-2.5 bg-[#1A365D] text-white rounded-lg font-medium hover:bg-[#142a4a] transition-colors disabled:opacity-50 text-sm"
            @click="demoLogin('doctor')"
          >
            {{ $t('demo.signInAsDoctor') }}
          </button>
        </div>
      </div>

      <div v-if="demoLoginEnabled" class="relative flex items-center mb-6">
        <div class="flex-1 border-t border-gray-300" />
        <span class="mx-3 text-xs text-gray-400 uppercase">{{ $t('demo.orSignIn') }}</span>
        <div class="flex-1 border-t border-gray-300" />
      </div>

      <form class="bg-white/60 backdrop-blur-sm rounded-2xl shadow-sm border border-white/80 p-6 space-y-4" @submit.prevent="handleLogin">
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('auth.email') }}</label>
          <input
            id="email"
            v-model="email"
            type="email"
            required
            class="w-full px-3 py-2 border border-[#c4d8bf] rounded-lg focus:ring-2 focus:ring-[#4A6741] focus:border-[#4A6741] outline-none bg-white/70"
            :placeholder="$t('auth.emailPlaceholder')"
          />
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ $t('auth.password') }}</label>
          <input
            id="password"
            v-model="password"
            type="password"
            required
            class="w-full px-3 py-2 border border-[#c4d8bf] rounded-lg focus:ring-2 focus:ring-[#4A6741] focus:border-[#4A6741] outline-none bg-white/70"
            :placeholder="$t('auth.passwordPlaceholder')"
          />
        </div>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <button
          type="submit"
          :disabled="loading"
          class="w-full py-2.5 bg-[#4A6741] text-white rounded-lg font-medium hover:bg-[#3d5636] transition-colors disabled:opacity-50"
        >
          {{ loading ? $t('auth.signingIn') : $t('auth.signIn') }}
        </button>
      </form>

      <p class="text-center text-sm text-gray-500 mt-4">
        {{ $t('auth.noAccount') }}
        <router-link to="/register" class="text-[#4A6741] font-medium hover:text-[#3d5636]">{{ $t('auth.signUp') }}</router-link>
      </p>
    </div>

    <!-- Footer -->
    <p class="mt-12 text-xs text-gray-300 relative z-10">{{ $t('app.footer') }}</p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useApi } from '@/composables/useApi';
import { useRouter, useRoute } from 'vue-router';

const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function handleLogin() {
    loading.value = true;
    error.value = '';
    try {
        await auth.login(email.value, password.value);
        const redirect = route.query.redirect || (auth.isDoctor ? '/doctor' : '/profile');
        router.push(redirect);
    } catch (err) {
        error.value = err.response?.data?.error?.message || t('auth.invalidCredentials');
    } finally {
        loading.value = false;
    }
}

const demoLoginEnabled = window.__APP_CONFIG__?.demoLoginEnabled ?? true;
const demoVisible = ref(false);
onMounted(() => {
    // Trigger entrance animation after mount
    requestAnimationFrame(() => { demoVisible.value = true; });
});

async function demoLogin(role) {
    loading.value = true;
    error.value = '';
    try {
        const api = useApi();
        const { data } = await api.post('/demo/start', { role });
        auth.user = data.data.user;
        auth.token = data.data.token;
        router.push(role === 'doctor' ? '/doctor' : '/profile');
    } catch (err) {
        error.value = err.response?.data?.error?.message || 'Demo data not seeded. Run: php artisan db:seed --class=DemoSeeder';
    } finally {
        loading.value = false;
    }
}
</script>

<style scoped>
.login-demo-section {
    animation: demo-glow 2.5s ease-in-out infinite alternate;
}

@keyframes demo-glow {
    from {
        box-shadow: 0 0 8px -2px rgb(16 185 129 / 0.3);
    }
    to {
        box-shadow: 0 0 20px -2px rgb(16 185 129 / 0.5);
    }
}
</style>

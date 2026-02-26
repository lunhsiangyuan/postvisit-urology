<template>
  <div class="min-h-screen bg-gray-100 px-4 py-10 sm:px-6">
    <div class="max-w-6xl mx-auto">
      <div class="text-center mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 flex items-center justify-center gap-3">
          <img src="/images/logo-icon.png" alt="" class="h-8" />
          {{ $t('demo.selectScenario') }}
        </h1>
        <p class="text-sm text-gray-500 mt-2 max-w-2xl mx-auto">
          {{ $t('demo.selectDescription') }}
        </p>
      </div>

      <div v-if="loadingScenarios" class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div
          v-for="i in 4"
          :key="i"
          class="bg-white rounded-xl overflow-hidden shadow-sm ring-2 ring-gray-100"
        >
          <div class="aspect-square bg-gradient-to-br from-gray-200 via-gray-100 to-gray-200 animate-pulse">
            <div class="w-full h-full flex items-center justify-center">
              <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" />
              </svg>
            </div>
          </div>
          <div class="px-3 py-2.5 sm:px-4 sm:py-3 space-y-2">
            <div class="h-4 bg-gray-200 rounded animate-pulse w-3/4"></div>
            <div class="h-3 bg-gray-100 rounded animate-pulse w-1/2"></div>
            <div class="h-3 bg-[#eef3ec] rounded animate-pulse w-full"></div>
          </div>
        </div>
      </div>

      <div v-else-if="fetchError" class="text-center py-20">
        <p class="text-red-600 mb-4">{{ fetchError }}</p>
        <button
          class="px-6 py-2 bg-[#4A6741] text-white rounded-lg hover:bg-[#3d5636] transition-colors"
          @click="loadScenarios"
        >
          {{ $t('demo.retry') }}
        </button>
      </div>

      <template v-else>
        <!-- Featured Scenarios -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
          <button
            v-for="scenario in featuredScenarios"
            :key="scenario.key"
            :disabled="startingScenario !== null"
            class="text-left bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-lg transition-all duration-200 disabled:opacity-60 disabled:cursor-wait group ring-2 ring-[#c4d8bf] hover:ring-[#4A6741]"
            @click="selectScenario(scenario.key)"
          >
            <ScenarioCard :scenario="scenario" :index="scenarioIndex(scenario)" :starting="startingScenario === scenario.key" />
          </button>
        </div>

        <!-- Specialty Filter -->
        <div class="mt-8 flex flex-wrap items-center gap-2">
          <span class="text-xs font-medium text-gray-400 uppercase tracking-wider mr-1">{{ $t('specialty.label') }}:</span>
          <button
            v-for="spec in allSpecialties"
            :key="spec.name"
            :class="[
              'px-3 py-1 rounded-full text-xs font-medium transition-all duration-150',
              spec.available
                ? activeSpecialty === spec.name
                  ? 'bg-[#4A6741] text-white shadow-sm'
                  : 'bg-white text-gray-600 hover:bg-[#f4f8f3] hover:text-[#4A6741] border border-gray-200'
                : 'bg-gray-100 text-gray-300 border border-gray-100 cursor-default'
            ]"
            @click="handleSpecialtyClick(spec)"
          >
            {{ $t('specialty.' + spec.name) }}
            <span v-if="spec.available && spec.count > 0" class="ml-1 text-[10px] opacity-60">{{ spec.count }}</span>
          </button>
        </div>

        <!-- More Scenarios -->
        <div v-if="otherScenarios.length > 0" class="mt-6">
          <button
            v-if="!showMore"
            class="w-full py-3 text-sm font-medium text-gray-500 hover:text-[#4A6741] transition-colors flex items-center justify-center gap-2 bg-white rounded-lg border border-gray-200 hover:border-[#c4d8bf]"
            @click="showMore = true"
          >
            <span>{{ $t('demo.showMore', { n: otherScenarios.length }) }}</span>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <div v-else>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
              <button
                v-for="scenario in filteredOtherScenarios"
                :key="scenario.key"
                :disabled="startingScenario !== null"
                class="text-left bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-lg transition-all duration-200 disabled:opacity-60 disabled:cursor-wait group"
                @click="selectScenario(scenario.key)"
              >
                <ScenarioCard :scenario="scenario" :index="scenarioIndex(scenario)" :starting="startingScenario === scenario.key" />
              </button>
            </div>

            <button
              class="mt-4 w-full py-2 text-xs text-gray-400 hover:text-gray-600 transition-colors"
              @click="showMore = false; activeSpecialty = null"
            >
              {{ $t('demo.showLess') }}
            </button>
          </div>
        </div>
      </template>

      <div v-if="startError" class="mt-6 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 text-center max-w-lg mx-auto">
        {{ startError }}
      </div>

      <!-- Disclaimer -->
      <p class="text-center text-[11px] text-gray-400 mt-8 max-w-2xl mx-auto leading-relaxed">
        {{ $t('demo.photoDisclaimer') }}
      </p>

      <div class="text-center mt-4">
        <router-link to="/login" class="text-sm text-gray-400 hover:text-[#4A6741] transition-colors">
          {{ $t('demo.backToSignIn') }}
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { useApi } from '@/composables/useApi';
import { useRouter } from 'vue-router';
import ScenarioCard from '@/components/ScenarioCard.vue';

const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const api = useApi();

const scenarios = ref([]);
const loadingScenarios = ref(true);
const fetchError = ref('');
const startingScenario = ref(null);
const startError = ref('');
const showMore = ref(false);
const activeSpecialty = ref(null);

const featuredScenarios = computed(() =>
  scenarios.value.filter(s => s.featured)
);

const otherScenarios = computed(() =>
  scenarios.value.filter(s => !s.featured)
);

const filteredOtherScenarios = computed(() => {
  if (!activeSpecialty.value) return otherScenarios.value;
  return otherScenarios.value.filter(s => s.specialty === activeSpecialty.value);
});

const KNOWN_SPECIALTIES = [
  'urology', 'cardiology', 'endocrinology', 'gastroenterology',
  'pulmonology', 'neurology', 'orthopedics', 'oncology', 'rheumatology',
];

const allSpecialties = computed(() => {
  const counts = {};
  scenarios.value.forEach(s => {
    counts[s.specialty] = (counts[s.specialty] || 0) + 1;
  });
  return KNOWN_SPECIALTIES.map(name => ({
    name,
    available: !!counts[name],
    count: counts[name] || 0,
  }));
});

function scenarioIndex(scenario) {
  return scenarios.value.indexOf(scenario);
}

function handleSpecialtyClick(spec) {
  if (!spec.available) return;
  if (activeSpecialty.value === spec.name) {
    activeSpecialty.value = null;
  } else {
    activeSpecialty.value = spec.name;
  }
  if (!showMore.value) showMore.value = true;
}

async function loadScenarios() {
  loadingScenarios.value = true;
  fetchError.value = '';
  try {
    const { data } = await api.get('/demo/scenarios', { skipErrorToast: true });
    scenarios.value = data.data;
  } catch (err) {
    fetchError.value = t('demo.loadError');
  } finally {
    loadingScenarios.value = false;
  }
}

async function selectScenario(key) {
  startingScenario.value = key;
  startError.value = '';
  try {
    const { data } = await api.post('/demo/start-scenario', { scenario: key });
    auth.user = data.data.user;
    auth.token = data.data.token;
    router.push('/profile');
  } catch (err) {
    startError.value = err.response?.data?.error?.message || t('demo.startError');
    startingScenario.value = null;
  }
}

onMounted(loadScenarios);
</script>

<template>
  <ClientOnly>
    <span class="inline-flex items-baseline">
      <span
        class="relative inline-block transition-[width] duration-600 ease-out"
        :style="{ width: containerWidth }"
      >
        <span class="block h-full overflow-hidden">
          <transition
            mode="out-in"
            enter-from-class="translate-y-full opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-full opacity-0"
            enter-active-class="transition duration-300 ease-out"
            leave-active-class="transition duration-300 ease-in"
          >
            <span :key="currentLanguageIndex" class="inline-block whitespace-nowrap">{{
              timeGreeting
            }}</span>
          </transition>
        </span>
        <span
          ref="measureElement"
          class="invisible absolute whitespace-nowrap"
          aria-hidden="true"
          >{{ timeGreeting }}</span
        ></span
      ><span class="inline-block">, {{ firstName }}!</span>
    </span>

    <template #fallback> Hello, {{ firstName }}!</template>
  </ClientOnly>
</template>

<script setup>
const { user } = useSanctumAuth();

// Static greeting data - no need to be reactive
const GREETINGS = Object.freeze({
  en: {
    morning: "Good Morning",
    afternoon: "Good Afternoon",
    evening: "Good Evening",
    night: "Good Night",
  },
  id: {
    morning: "Selamat Pagi",
    afternoon: "Selamat Siang",
    evening: "Selamat Sore",
    night: "Selamat Malam",
  },
  fr: {
    morning: "Bonjour",
    afternoon: "Bon après-midi",
    evening: "Bonsoir",
    night: "Bonne nuit",
  },
  ja: {
    morning: "おはようございます",
    afternoon: "こんにちは",
    evening: "こんばんは",
    night: "おやすみなさい",
  },
  ko: {
    morning: "좋은 아침",
    afternoon: "좋은 오후",
    evening: "좋은 저녁",
    night: "안녕히 주무세요",
  },
  zh: {
    morning: "早上好",
    afternoon: "下午好",
    evening: "晚上好",
    night: "晚安",
  },
  es: {
    morning: "Buenos días",
    afternoon: "Buenas tardes",
    evening: "Buenas tardes",
    night: "Buenas noches",
  },
});

const LANGUAGES = Object.keys(GREETINGS);
const INTERVAL_DURATION = 4000;

// Only language index needs to be reactive
const currentLanguageIndex = ref(Math.floor(Math.random() * LANGUAGES.length));

// Get current time period
const getTimePeriod = () => {
  const hour = new Date().getHours();
  if (hour >= 5 && hour < 12) {
    return "morning";
  }
  if (hour >= 12 && hour < 17) {
    return "afternoon";
  }
  if (hour >= 17 && hour < 21) {
    return "evening";
  }
  return "night";
};

// Computed greeting based on language and time
const timeGreeting = computed(() => {
  const language = LANGUAGES[currentLanguageIndex.value];
  const period = getTimePeriod();
  return GREETINGS[language][period];
});

// Extract first name from user
const firstName = computed(() => {
  const fullName = user.value?.name || "User";
  return fullName.split(" ")[0];
});

// Width animation management
const measureElement = ref(null);
const containerWidth = ref("auto");

watch(
  timeGreeting,
  () => {
    nextTick(() => {
      if (measureElement.value) {
        containerWidth.value = `${measureElement.value.offsetWidth}px`;
      }
    });
  },
  { immediate: true }
);

// Language rotation interval
let intervalId;
onMounted(() => {
  intervalId = setInterval(() => {
    currentLanguageIndex.value = (currentLanguageIndex.value + 1) % LANGUAGES.length;
  }, INTERVAL_DURATION);
});

onUnmounted(() => {
  if (intervalId) {
    clearInterval(intervalId);
  }
});
</script>

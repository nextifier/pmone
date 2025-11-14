<template>
  <div class="mx-auto max-w-3xl space-y-6 pt-4 pb-16">
    <!-- Greeting Section -->
    <div class="rounded-2xl border bg-gradient-to-br from-primary/5 to-primary/10 p-6 dark:from-primary/10 dark:to-primary/20">
      <h2 class="text-2xl font-bold sm:text-3xl">
        {{ greeting }}
      </h2>
      <p class="mt-1 text-sm text-muted-foreground">
        {{ languageName }}
      </p>
    </div>

    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:dashboard-circle" class="size-5 sm:size-6" />
      <h1 class="page-title">Dashboard</h1>
      <div class="flex items-center justify-center gap-x-1.5 rounded-lg border px-2 py-1.5">
        <Icon name="hugeicons:timer-01" class="size-4 shrink-0" />
        <span class="text-xs font-medium tracking-tight uppercase">Coming soon</span>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
      <div
        v-for="(_, index) in 40"
        :key="index"
        class="bg-pattern-diagonal border-border/80 aspect-4/5 rounded-2xl border"
      ></div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("dashboard");

const { user, isAuthenticated } = useSanctumAuth();

// Multilingual greetings
const greetings = {
  en: {
    name: "English",
    morning: "Good Morning",
    afternoon: "Good Afternoon",
    evening: "Good Evening",
    night: "Good Night",
  },
  id: {
    name: "Bahasa Indonesia",
    morning: "Selamat Pagi",
    afternoon: "Selamat Siang",
    evening: "Selamat Sore",
    night: "Selamat Malam",
  },
  fr: {
    name: "Français",
    morning: "Bonjour",
    afternoon: "Bon après-midi",
    evening: "Bonsoir",
    night: "Bonne nuit",
  },
  ja: {
    name: "日本語",
    morning: "おはようございます",
    afternoon: "こんにちは",
    evening: "こんばんは",
    night: "おやすみなさい",
  },
  ko: {
    name: "한국어",
    morning: "좋은 아침",
    afternoon: "좋은 오후",
    evening: "좋은 저녁",
    night: "안녕히 주무세요",
  },
  zh: {
    name: "中文",
    morning: "早上好",
    afternoon: "下午好",
    evening: "晚上好",
    night: "晚安",
  },
  es: {
    name: "Español",
    morning: "Buenos días",
    afternoon: "Buenas tardes",
    evening: "Buenas tardes",
    night: "Buenas noches",
  },
};

// Get random language on component mount
const languages = Object.keys(greetings);
const randomLanguage = languages[Math.floor(Math.random() * languages.length)];
const selectedLanguage = greetings[randomLanguage];

// Get time-based greeting
const getTimeBasedGreeting = () => {
  const hour = new Date().getHours();

  if (hour >= 5 && hour < 12) {
    return selectedLanguage.morning;
  }
  if (hour >= 12 && hour < 17) {
    return selectedLanguage.afternoon;
  }
  if (hour >= 17 && hour < 21) {
    return selectedLanguage.evening;
  }
  return selectedLanguage.night;
};

// Compose the greeting message
const greeting = computed(() => {
  const timeGreeting = getTimeBasedGreeting();
  const userName = user.value?.name || "User";
  return `${timeGreeting}, ${userName}`;
});

const languageName = computed(() => selectedLanguage.name);
</script>

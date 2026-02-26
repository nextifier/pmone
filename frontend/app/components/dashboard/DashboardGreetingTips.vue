<template>
  <div>
    <h2 class="page-title">
      <DashboardGreeting />
    </h2>

    <div v-if="visibleTips.length" class="mt-3 flex w-full max-w-fit flex-col gap-2">
      <div
        v-for="tip in visibleTips"
        :key="tip.key"
        class="bg-card border-border flex w-full items-start gap-2 rounded-lg border p-3"
      >
        <Icon :name="tip.icon" class="text-muted-foreground size-5 shrink-0 translate-y-0" />
        <p class="text-muted-foreground tracking-tight text-pretty sm:text-sm">
          {{ tip.text }}
          <NuxtLink :to="tip.href" class="text-foreground font-medium hover:underline">
            {{ tip.action }}
          </NuxtLink>
        </p>
        <button
          class="text-muted-foreground/70 hover:text-foreground ml-auto flex size-5 shrink-0 translate-x-1 items-center justify-center rounded-full"
          @click="dismissTip(tip.key)"
        >
          <Icon name="hugeicons:cancel-01" class="size-3.5" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  tipDefinitions: { type: Array, default: () => [] },
  tips: { type: Object, default: null },
  loading: { type: Boolean, default: false },
});

const SNOOZE_DAYS = 7;
const STORAGE_KEY = "dashboard_dismissed_tips";

const dismissedKeys = ref(new Set());

function getDismissedFromStorage() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return {};
    return JSON.parse(raw);
  } catch {
    return {};
  }
}

function dismissTip(key) {
  dismissedKeys.value = new Set([...dismissedKeys.value, key]);
  const stored = getDismissedFromStorage();
  stored[key] = Date.now();
  localStorage.setItem(STORAGE_KEY, JSON.stringify(stored));
}

function isSnoozed(key) {
  const stored = getDismissedFromStorage();
  if (!stored[key]) return false;
  const elapsed = Date.now() - stored[key];
  return elapsed < SNOOZE_DAYS * 24 * 60 * 60 * 1000;
}

const visibleTips = computed(() => {
  if (props.loading || !props.tips) return [];
  return props.tipDefinitions.filter(
    (t) => props.tips[t.key] === false && !dismissedKeys.value.has(t.key) && !isSnoozed(t.key)
  );
});

onMounted(() => {
  const stored = getDismissedFromStorage();
  const snoozed = new Set();
  for (const key in stored) {
    if (isSnoozed(key)) snoozed.add(key);
  }
  dismissedKeys.value = snoozed;
});
</script>

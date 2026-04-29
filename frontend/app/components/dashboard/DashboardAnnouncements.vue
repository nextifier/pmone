<template>
  <div v-if="visibleAnnouncements.length" class="flex w-full max-w-fit flex-col gap-2">
    <div
      v-for="announcement in visibleAnnouncements"
      :key="announcement.key"
      class="bg-card border-border flex w-full items-start gap-3 rounded-lg border p-3"
    >
      <Icon :name="announcement.icon" class="text-muted-foreground size-5 shrink-0 translate-y-0.5" />
      <div class="flex flex-col gap-1">
        <p class="text-foreground tracking-tight font-medium text-pretty sm:text-sm">
          {{ announcement.title }}
        </p>
        <p class="text-muted-foreground tracking-tight text-pretty text-xs sm:text-sm">
          {{ announcement.tutorial }}
        </p>
      </div>
      <button
        class="text-muted-foreground/70 hover:text-foreground ml-auto flex size-5 shrink-0 translate-x-1 items-center justify-center rounded-full"
        @click="dismissAnnouncement(announcement.key)"
      >
        <Icon name="lucide:x" class="size-3.5" />
      </button>
    </div>
  </div>
</template>

<script setup>
const SNOOZE_DAYS = 30;
const STORAGE_KEY = "dashboard_dismissed_announcements";

const ANNOUNCEMENTS = [
  {
    key: "rundown_upload_2026_04",
    icon: "hugeicons:calendar-04",
    title: "Rundown event sekarang bisa kamu upload sendiri.",
    tutorial:
      "Buka event yang kamu kelola, masuk ke tab Content lalu pilih Rundown. Buat rundown satu per satu sesuai jadwal acaramu.",
  },
  {
    key: "visitor_eguide_2026_04",
    icon: "hugeicons:book-open-01",
    title: "Visitor E-Guide juga sudah bisa kamu upload.",
    tutorial:
      "Dari halaman event, klik Edit Details lalu scroll ke section Visitor E-Guide. Upload file PDF kamu (max 20MB), terus simpan.",
  },
];

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

function dismissAnnouncement(key) {
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

const visibleAnnouncements = computed(() => {
  return ANNOUNCEMENTS.filter((a) => !dismissedKeys.value.has(a.key) && !isSnoozed(a.key));
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

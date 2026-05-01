<template>
  <div v-if="announcements.length" class="flex w-full max-w-fit flex-col gap-2">
    <div
      v-for="item in announcements"
      :key="item.id"
      class="bg-card border-border flex w-full items-start gap-3 rounded-lg border p-3"
    >
      <div
        v-if="item.image?.sm?.url"
        class="size-12 shrink-0 overflow-hidden rounded-md"
      >
        <img
          :src="item.image.sm.url"
          :alt="item.title"
          class="size-full object-cover"
          loading="lazy"
        />
      </div>
      <div
        v-else-if="item.icon"
        :class="[
          'flex size-9 shrink-0 items-center justify-center rounded-md',
          typeBgClass(item.type),
        ]"
      >
        <Icon :name="item.icon" :class="['size-5', typeIconClass(item.type)]" />
      </div>

      <div class="flex min-w-0 flex-1 flex-col gap-1">
        <p class="text-foreground tracking-tight font-medium text-pretty sm:text-sm">
          {{ item.title }}
        </p>
        <p
          v-if="item.description"
          class="text-muted-foreground tracking-tight text-pretty sm:text-sm"
        >
          {{ item.description }}
        </p>
        <div v-if="item.cta_actions?.length" class="mt-1 flex flex-wrap items-center gap-2">
          <NuxtLink
            v-for="(cta, idx) in item.cta_actions"
            :key="idx"
            :to="cta.url"
            :class="ctaClass(cta.style)"
          >
            <Icon v-if="cta.icon" :name="cta.icon" class="size-3.5" />
            {{ cta.label }}
          </NuxtLink>
        </div>
      </div>

      <button
        v-if="item.is_dismissible"
        class="text-muted-foreground/70 hover:text-foreground ml-auto flex size-5 shrink-0 translate-x-1 items-center justify-center rounded-full"
        @click="dismiss(item)"
      >
        <Icon name="lucide:x" class="size-3.5" />
      </button>
    </div>
  </div>
</template>

<script setup>
const client = useSanctumClient();

const announcements = ref([]);

const TYPE_BG = {
  info: "bg-info/15",
  success: "bg-success/15",
  warning: "bg-warning/15",
  error: "bg-destructive/15",
  marketing: "bg-muted",
};
const TYPE_ICON = {
  info: "text-info",
  success: "text-success",
  warning: "text-warning",
  error: "text-destructive",
  marketing: "text-muted-foreground",
};

function typeBgClass(type) {
  return TYPE_BG[type] || TYPE_BG.info;
}
function typeIconClass(type) {
  return TYPE_ICON[type] || TYPE_ICON.info;
}

function ctaClass(style) {
  if (style === "button-primary") {
    return "bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium tracking-tight transition-colors sm:text-sm";
  }
  if (style === "button-outline") {
    return "border-border hover:bg-muted inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium tracking-tight transition-colors sm:text-sm";
  }
  return "text-foreground inline-flex items-center gap-1 text-xs font-medium tracking-tight hover:underline sm:text-sm";
}

async function dismiss(item) {
  announcements.value = announcements.value.filter((a) => a.id !== item.id);
  try {
    await client(`/api/dashboard/announcements/${item.id}/dismiss`, { method: "POST" });
  } catch (error) {
    console.error("Failed to dismiss announcement:", error);
  }
}

async function fetchAnnouncements() {
  try {
    const response = await client("/api/dashboard/announcements");
    announcements.value = response?.data || [];
  } catch (error) {
    console.error("Failed to fetch announcements:", error);
    announcements.value = [];
  }
}

onMounted(fetchAnnouncements);
</script>

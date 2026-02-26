<template>
  <div class="border-border hover:bg-muted/50 group relative flex flex-col gap-y-3 rounded-xl border p-4 transition">
    <NuxtLink
      :to="`/projects/${username}/events/${event.slug}`"
      class="absolute inset-0 rounded-xl"
    />
    <div class="flex items-start gap-x-3">
      <div
        v-if="event.poster_image"
        class="bg-muted squircle size-12 shrink-0 overflow-hidden rounded-lg"
      >
        <img
          :src="event.poster_image?.sm || event.poster_image?.url"
          :alt="event.title"
          class="size-full object-cover"
          loading="lazy"
        />
      </div>
      <div v-else class="bg-muted text-muted-foreground flex size-12 shrink-0 items-center justify-center rounded-lg">
        <Icon name="hugeicons:calendar-03" class="size-5" />
      </div>

      <div class="min-w-0 flex-1 space-y-1">
        <h3 class="truncate font-medium tracking-tight">{{ event.title }}</h3>
        <div class="text-muted-foreground flex flex-wrap items-center gap-x-2 text-xs tracking-tight">
          <span v-if="event.edition_label" class="bg-primary/10 text-primary rounded-full px-1.5 py-0.5 text-[11px] font-medium">
            {{ event.edition_label }}
          </span>
          <span v-if="event.date_label">{{ event.date_label }}</span>
        </div>
      </div>
    </div>

    <div v-if="event.location" class="text-muted-foreground flex items-center gap-x-1.5 text-xs tracking-tight">
      <Icon name="hugeicons:location-01" class="size-3.5 shrink-0" />
      <span class="truncate">{{ event.location }}</span>
    </div>

    <div class="flex items-center justify-between">
      <div class="flex items-center gap-x-1.5">
        <span
          :class="[
            'rounded-full px-2 py-0.5 text-[11px] font-medium',
            statusClasses[event.status] || 'bg-muted text-muted-foreground',
          ]"
        >
          {{ event.status }}
        </span>
        <span
          v-if="event.is_active"
          class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
        >
          Active
        </span>
      </div>
      <div class="relative z-10 flex items-center gap-x-1.5">
        <span
          v-if="event.visibility === 'public'"
          class="text-muted-foreground text-[11px]"
        >
          Public
        </span>
        <button
          v-if="!event.is_active"
          type="button"
          :disabled="settingActive"
          @click.prevent="handleSetActive"
          class="text-muted-foreground hover:text-foreground rounded px-1.5 py-0.5 text-[11px] font-medium transition hover:bg-emerald-100 hover:text-emerald-700 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-400"
        >
          Set Active
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const props = defineProps({
  event: {
    type: Object,
    required: true,
  },
  username: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(["active-changed"]);
const client = useSanctumClient();
const settingActive = ref(false);

async function handleSetActive() {
  settingActive.value = true;
  try {
    await client(`/api/projects/${props.username}/events/${props.event.slug}/set-active`, {
      method: "POST",
    });
    toast.success(`${props.event.title} set as active edition`);
    emit("active-changed");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to set as active");
  } finally {
    settingActive.value = false;
  }
}

const statusClasses = {
  draft: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400",
  published: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400",
  archived: "bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400",
  cancelled: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
};
</script>

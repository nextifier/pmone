<template>
  <NuxtLink
    :to="`/announcements/${announcement.id}/edit`"
    class="flex items-start gap-x-2 transition hover:opacity-80"
  >
    <div
      v-if="announcement.image?.sm?.url"
      class="bg-muted border-border size-10 shrink-0 overflow-hidden rounded-md border"
    >
      <img
        :src="announcement.image.sm.url"
        :alt="announcement.title"
        class="size-full object-cover select-none"
        loading="lazy"
      />
    </div>
    <div
      v-else-if="announcement.icon"
      class="bg-muted text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-md"
    >
      <Icon :name="announcement.icon" class="size-5" />
    </div>

    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-x-1.5">
        <span
          class="text-xs font-medium tracking-tight capitalize"
          :class="statusClass"
        >
          {{ announcement.status }}
        </span>
        <span
          class="text-muted-foreground rounded-full bg-muted px-1.5 py-0.5 text-xs font-medium tracking-tight capitalize"
        >
          {{ announcement.type }}
        </span>
        <span
          v-if="announcement.is_global"
          class="text-muted-foreground rounded-full bg-muted px-1.5 py-0.5 text-xs font-medium tracking-tight"
        >
          Global
        </span>
      </div>
      <p class="line-clamp-1 text-sm tracking-tight">{{ announcement.title }}</p>
    </div>
  </NuxtLink>
</template>

<script setup>
const props = defineProps({
  announcement: { type: Object, required: true },
});

const statusClass = computed(() => {
  switch (props.announcement.status) {
    case "published":
      return "text-success-foreground";
    case "draft":
      return "text-warning-foreground";
    case "archived":
      return "text-muted-foreground";
    default:
      return "text-muted-foreground";
  }
});
</script>

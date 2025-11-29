<template>
  <div class="flex flex-col gap-y-0.5 overflow-hidden">
    <div class="flex items-center gap-x-0.5">
      <NuxtLink
        :to="`/short-links/${link.slug}`"
        class="text-muted-foreground inline text-sm tracking-tight"
      >
        {{ useRuntimeConfig().public.siteUrl.replace(/^https?:\/\//, "") }}/<span
          class="text-foreground font-medium"
          >{{ link.slug }}</span
        >
      </NuxtLink>

      <ButtonCopy :text="`${useRuntimeConfig().public.siteUrl}/${link.slug}`" />
    </div>

    <div class="text-muted-foreground line-clamp-1 inline-flex items-center gap-x-0.5">
      <Icon name="hugeicons:arrow-move-down-right" class="size-4 shrink-0 -translate-y-1" />
      <span class="text-xs tracking-tight">{{
        link.destination_url.replace(/^https?:\/\//, "")
      }}</span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  link: {
    type: Object,
    required: true,
  },
});
</script>

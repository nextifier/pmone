<script setup>
defineProps({
  preset: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <NuxtLink
    :to="`/shaders/${preset.id}`"
    data-slot="card-frame"
    class="bg-card text-card-foreground relative flex flex-col rounded-2xl border shadow-xs/5 not-dark:bg-clip-padding before:pointer-events-none before:absolute before:inset-0 before:rounded-[calc(var(--radius-2xl)-1px)] before:bg-muted/72 before:shadow-[0_1px_--theme(--color-black/4%)] dark:before:shadow-[0_-1px_--theme(--color-white/6%)] [--radius-2xl:1rem]"
  >
    <div
      data-slot="card-frame-header"
      class="relative z-10 grid auto-rows-min grid-rows-[auto_1fr] items-start gap-x-4 px-6 py-4"
    >
      <h2 class="self-center text-base font-semibold tracking-tight">{{ preset.title }}</h2>
      <p
        class="text-muted-foreground line-clamp-2 self-center text-sm tracking-tight sm:h-[2lh]"
      >
        {{ preset.description }}
      </p>
    </div>

    <div
      data-slot="card"
      class="text-card-foreground relative flex min-h-55 flex-1 flex-col flex-wrap overflow-hidden rounded-2xl border shadow-xs/5 not-dark:bg-clip-padding bg-[color-mix(in_srgb,var(--color-card),var(--color-sidebar))] dark:bg-background -m-px before:pointer-events-none before:absolute before:inset-0 before:rounded-[calc(var(--radius-2xl)-1px)] before:shadow-[0_1px_--theme(--color-black/4%)] dark:before:shadow-[0_-1px_--theme(--color-white/6%)] pointer-events-none"
    >
      <div
        data-slot="card-panel"
        class="relative z-10 flex flex-1 items-stretch justify-center overflow-hidden p-0 [--border:--alpha(var(--color-black)/7%)] dark:[--border:--alpha(var(--color-white)/3%)]"
      >
        <img
          :src="preset.thumbnail"
          :alt="preset.title"
          loading="lazy"
          class="size-full rounded-lg object-cover"
        />
      </div>
    </div>
  </NuxtLink>
</template>

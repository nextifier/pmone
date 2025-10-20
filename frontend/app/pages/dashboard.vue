<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:dashboard-circle" class="size-5 sm:size-6" />
      <h1 class="page-title">Dashboard</h1>
      <div class="flex items-center justify-center gap-x-1.5 rounded-lg border px-2 py-1.5">
        <Icon name="hugeicons:timer-01" class="size-4 shrink-0" />
        <span class="text-xs font-medium tracking-tight uppercase">Coming soon</span>
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <button
        class="bg-muted rounded-lg border px-2 py-1 text-sm font-medium tracking-tight active:scale-98"
        @click="
          toast('Users imported successfully', {
            description:
              'You can focus on the toast area by pressing ⌥/alt + T. You can override it by providing an array of event.code values for each key.',
          })
        "
      >
        Toast Normal
      </button>

      <button
        class="bg-muted rounded-lg border px-2 py-1 text-sm font-medium tracking-tight active:scale-98"
        @click="
          toast.success('Users imported successfully', {
            description:
              'You can focus on the toast area by pressing ⌥/alt + T. You can override it by providing an array of event.code values for each key.',
          })
        "
      >
        Toast Success
      </button>

      <button
        class="bg-muted rounded-lg border px-2 py-1 text-sm font-medium tracking-tight active:scale-98"
        @click="toast.success('Users imported successfully', {})"
      >
        Toast Success (no description)
      </button>

      <button
        class="bg-muted rounded-lg border px-2 py-1 text-sm font-medium tracking-tight active:scale-98"
        @click="
          toast.info('Users imported successfully', {
            description:
              'You can focus on the toast area by pressing ⌥/alt + T. You can override it by providing an array of event.code values for each key.',
          })
        "
      >
        Toast Info
      </button>

      <button
        class="bg-muted rounded-lg border px-2 py-1 text-sm font-medium tracking-tight active:scale-98"
        @click="
          toast.warning('Users imported successfully', {
            description:
              'You can focus on the toast area by pressing ⌥/alt + T. You can override it by providing an array of event.code values for each key.',
          })
        "
      >
        Toast Warning
      </button>

      <button
        class="bg-muted rounded-lg border px-2 py-1 text-sm font-medium tracking-tight active:scale-98"
        @click="
          toast.error('Users imported successfully', {
            description:
              'You can focus on the toast area by pressing ⌥/alt + T. You can override it by providing an array of event.code values for each key.',
          })
        "
      >
        Toast Error
      </button>

      <button
        class="bg-muted rounded-lg border px-2 py-1 text-sm font-medium tracking-tight active:scale-98"
        @click="
          toast.loading('Users imported successfully', {
            description:
              'You can focus on the toast area by pressing ⌥/alt + T. You can override it by providing an array of event.code values for each key.',
          })
        "
      >
        Toast Loading
      </button>

      <button
        class="bg-muted rounded-lg border px-2 py-1 text-sm font-medium tracking-tight active:scale-98"
        @click="
          toast('Event has been created', {
            description: 'Sunday, December 03, 2023 at 9:00 AM',
            action: {
              label: 'Undo',
              onClick: () => console.log('Undo'),
            },
          })
        "
      >
        Toast with Action
      </button>
    </div>

    <div class="border-border text-foreground w-full overflow-x-scroll rounded-xl border p-4">
      <pre class="text-foreground/80 text-sm !leading-[1.5]">{{ user }}</pre>
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

import { toast } from "vue-sonner";
</script>

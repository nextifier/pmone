<template>
  <div
    class="bg-background text-foreground min-h-screen-offset flex flex-col items-center justify-center overflow-hidden"
  >
    <div class="container flex flex-col items-center justify-center gap-y-3 text-center">
      <span v-if="error?.statusCode" class="text-sm">
        {{ error.statusCode }}
      </span>

      <h1
        v-if="error?.statusMessage"
        class="text-primary w-full text-4xl font-bold tracking-tighter wrap-break-word"
      >
        {{ error.statusMessage }}
      </h1>

      <p class="mx-auto mt-1 max-w-2xl tracking-tight text-pretty">
        We couldn't find the page you're looking for. It might have moved, been renamed, or maybe it
        never existed in the first place.
      </p>

      <pre
        v-if="error?.stack && error?.statusCode === 500"
        class="text-muted-foreground mt-3 w-full max-w-xl overflow-auto rounded-2xl border px-4 py-6 text-left text-xs leading-normal!"
        >{{ error.stack }}</pre
      >

      <button
        @click="() => clearError({ redirect: '/' })"
        class="bg-primary/8 text-foreground hover:bg-primary/14 mt-2 flex items-center gap-x-1 rounded-md px-3 py-2 text-sm font-medium tracking-tight transition active:scale-98"
      >
        <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
        <span>Back to Home</span>
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  error: {
    type: Object,
    default: null,
  },
});
</script>

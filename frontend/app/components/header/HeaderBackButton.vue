<template>
  <ButtonBack :destination="destination" :force-destination="forceDestination">
    <template #default="{ goBack }">
      <button
        type="button"
        aria-label="Go back"
        @click="goBack"
        class="text-primary/80 hover:text-primary sm:bg-card bg-muted border-border flex aspect-square h-9 shrink-0 items-center justify-center gap-x-1 rounded-full px-1 text-sm tracking-tight transition active:scale-98 sm:aspect-auto sm:h-8 sm:rounded-lg sm:border"
      >
        <Icon name="hugeicons:arrow-left-02" class="size-5 shrink-0" />
        <KbdGroup>
          <Kbd>B</Kbd>
        </KbdGroup>
      </button>
    </template>
  </ButtonBack>
</template>

<script setup>
/**
 * The back control in the app header. Extracted from HeaderBreadcrumb so the
 * project chain and the simpler page contexts (form builder and friends) cannot
 * drift into two slightly different buttons.
 */
defineProps({
  destination: { type: String, default: null },
  forceDestination: { type: Boolean, default: false },
});
</script>

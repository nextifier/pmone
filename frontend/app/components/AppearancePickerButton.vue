<script setup lang="ts">
import AppearanceCustomizer from "@/components/appearance/AppearanceCustomizer.vue";
import AppearanceDialogs from "@/components/appearance/AppearanceDialogs.vue";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
</script>

<template>
  <Popover :modal="false">
    <Tippy>
      <PopoverTrigger as-child>
        <button
          type="button"
          aria-label="Appearance"
          class="hover:bg-muted flex size-8 items-center justify-center rounded-lg transition-colors motion-safe:active:scale-98"
        >
          <Icon name="hugeicons:paint-board" class="text-foreground size-4.5 shrink-0" />
        </button>
      </PopoverTrigger>
      <template #content>
        <span class="inline-flex items-center gap-x-1.5 tracking-tight">Appearance</span>
      </template>
    </Tippy>
    <PopoverContent align="end" :collision-padding="8" class="w-72 rounded-xl p-3">
      <AppearanceCustomizer embedded />
    </PopoverContent>
  </Popover>

  <!-- Reset + Open-Preset dialogs, mounted once here (present wherever the
       customizer can be triggered) so they never duplicate across instances. -->
  <AppearanceDialogs />
</template>

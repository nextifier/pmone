<script setup lang="ts">
interface Props {
  title?: string;
  size?: "lg" | "md";
}

withDefaults(defineProps<Props>(), {
  title: "pmone.id",
  size: "md",
});
</script>

<template>
  <div
    class="bg-card ring-border overflow-hidden rounded-xl ring-1"
    :class="size === 'lg' ? '' : ''"
  >
    <!-- Title bar -->
    <div class="border-border flex items-center gap-3 border-b px-4 py-2.5">
      <div class="flex items-center gap-1.5">
        <div class="size-2.5 rounded-full bg-red-500" />
        <div class="size-2.5 rounded-full bg-yellow-500" />
        <div class="size-2.5 rounded-full bg-green-500" />
      </div>
      <div class="bg-muted flex-1 rounded-md px-3 py-1">
        <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ title }}</span>
      </div>
    </div>

    <!-- Video placeholder -->
    <div
      class="bg-muted/30 flex items-center justify-center"
      :class="size === 'lg' ? 'aspect-[16/9] sm:aspect-[16/8]' : 'aspect-video'"
    >
      <div class="flex flex-col items-center gap-3">
        <div
          class="bg-background ring-border flex items-center justify-center rounded-full ring-1"
          :class="size === 'lg' ? 'size-16' : 'size-12'"
        >
          <Icon
            name="hugeicons:play"
            :class="size === 'lg' ? 'size-6' : 'size-5'"
            class="text-muted-foreground ml-0.5"
          />
        </div>
        <span class="text-muted-foreground text-xs tracking-tight sm:text-sm"
          >Video coming soon</span
        >
      </div>
    </div>
  </div>
</template>

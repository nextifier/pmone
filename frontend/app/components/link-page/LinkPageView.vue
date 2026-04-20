<template>
  <div
    class="relative mx-auto flex min-h-dvh max-w-lg flex-col justify-between gap-y-8 pt-2 pb-10 sm:pt-4"
  >
    <!-- Title & Description -->
    <div class="flex items-start gap-x-2 p-4">
      <div class="flex grow flex-col gap-y-1">
        <h1 class="text-primary text-2xl leading-[1.2]! font-medium tracking-tighter text-balance">
          {{ linkPage.title }}
        </h1>
        <p v-if="linkPage.description" class="text-body tracking-tight text-pretty">
          {{ linkPage.description }}
        </p>
      </div>
      <ColorModeToggle class="shrink-0" />
    </div>

    <!-- Links -->
    <div class="grid grid-cols-1 gap-y-2 px-4">
      <a
        v-for="item in activeItems"
        :key="item.id"
        :href="item.url"
        target="_blank"
        rel="noopener noreferrer"
        class="border-border flex items-center gap-3 rounded-xl border p-1 transition duration-300 ease-out hover:scale-105 active:scale-98"
        @click="$emit('trackClick', item)"
      >
        <div v-if="item.poster" class="w-16 shrink-0">
          <img
            :src="item.poster.sm || item.poster.url"
            :alt="item.label"
            class="w-full rounded-lg object-contain"
          />
        </div>
        <div class="flex grow flex-col gap-y-1" :class="{ 'py-2 pl-3': !item.poster }">
          <h2 class="text-base leading-tight! font-medium tracking-tighter">{{ item.label }}</h2>
          <p
            v-if="item.description"
            class="text-muted-foreground line-clamp-2 text-xs tracking-tight sm:text-sm"
          >
            {{ item.description }}
          </p>
        </div>
        <span class="bg-muted mr-1 flex size-9 shrink-0 items-center justify-center rounded-full">
          <Icon name="lucide:arrow-up-right" class="text-muted-foreground size-4 shrink-0" />
        </span>
      </a>
    </div>
  </div>
</template>

<script setup>
import { ColorModeToggle } from "@/components/ui/color-mode-toggle";
const props = defineProps({
  linkPage: { type: Object, required: true },
});

defineEmits(["trackClick"]);

const { metaSymbol } = useShortcuts();

const activeItems = computed(() => {
  return (props.linkPage.items || []).filter((item) => item.is_active);
});
</script>

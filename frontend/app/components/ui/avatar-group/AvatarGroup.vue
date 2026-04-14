<template>
  <div
    data-slot="avatar-group"
    :style="styleVars"
    :class="cn('avatar-group isolate flex', firstOnTop && 'avatar-group-first-on-top', props.class)"
    role="group"
    :aria-label="ariaLabel"
  >
    <Avatar
      v-for="(item, i) in displayed"
      :key="item.id ?? item.name ?? i"
      :model="item"
      :style="firstOnTop ? { zIndex: displayed.length - i } : undefined"
    />

    <template v-if="hiddenItems.length > 0">
      <slot name="overflow" :count="hiddenItems.length" :hiddenItems="hiddenItems">
        <HoverCard :open-delay="100" :close-delay="150">
          <HoverCardTrigger as-child>
            <button
              type="button"
              data-slot="avatar-group-overflow"
              class="avatar-group-overflow bg-muted outline-inside text-muted-foreground hover:bg-border focus-visible:ring-ring relative flex shrink-0 items-center justify-center leading-none font-medium tracking-tight select-none focus-visible:ring-2 focus-visible:outline-none"
              :style="{
                zIndex: firstOnTop ? 0 : undefined,
                fontSize: `calc(var(--avatar-size) * 0.4)`,
              }"
              :aria-label="`${hiddenItems.length} more`"
            >
              +{{ hiddenItems.length }}
            </button>
          </HoverCardTrigger>
          <HoverCardContent class="w-auto min-w-48 p-1" align="center">
            <div class="flex max-h-64 flex-col gap-y-0.5 overflow-y-auto">
              <div
                v-for="(item, i) in hiddenItems"
                :key="item.id ?? item.name ?? i"
                class="flex items-center gap-x-2 rounded-md px-2 py-1"
              >
                <Avatar :model="item" circle no-tooltip class="size-6 shrink-0" />
                <span class="text-sm tracking-tight whitespace-nowrap">{{ item.name }}</span>
              </div>
            </div>
          </HoverCardContent>
        </HoverCard>
      </slot>
    </template>
  </div>
</template>

<script setup lang="ts" generic="T extends AvatarModel = AvatarModel">
import { cn } from "@/lib/utils";
import type { HTMLAttributes } from "vue";
import { computed, provide } from "vue";

interface AvatarModel {
  id?: string | number;
  name?: string;
  profile_image?: Record<string, string> | null;
}

interface AvatarGroupProps {
  items?: T[];
  size?: number;
  overlap?: number;
  gap?: number;
  max?: number;
  firstOnTop?: boolean;
  colorful?: boolean;
  showTooltip?: boolean;
  label?: string;
  class?: HTMLAttributes["class"];
}

const props = withDefaults(defineProps<AvatarGroupProps>(), {
  size: 2.5,
  overlap: 0.2,
  gap: 2,
  firstOnTop: true,
  colorful: true,
  showTooltip: true,
  items: () => [],
});

defineSlots<{
  overflow?: (scope: { count: number; hiddenItems: T[] }) => unknown;
}>();

provide("avatarGroupContext", {
  circle: true,
  get showTooltip() {
    return props.showTooltip;
  },
  get colorful() {
    return props.colorful;
  },
});

const displayed = computed(() => props.items.slice(0, props.max));

const hiddenItems = computed(() => (props.max == null ? [] : props.items.slice(props.max)));

const ariaLabel = computed(() => {
  if (props.label) return props.label;
  const count = props.items.length;
  return `Avatar group with ${count} member${count === 1 ? "" : "s"}`;
});

const styleVars = computed(() => ({
  "--avatar-size": `${props.size}rem`,
  "--avatar-overlap": String(props.overlap),
  "--avatar-gap": `${props.gap}px`,
}));
</script>

<style scoped>
.avatar-group > :deep(*) {
  width: var(--avatar-size);
  height: var(--avatar-size);
  border-radius: 9999px;
}

.avatar-group > :deep(*:not(:first-child)) {
  margin-left: calc(var(--avatar-size) * var(--avatar-overlap) * -1);
}

.avatar-group-first-on-top > :deep(*:not(:first-child)) {
  --_mask-x: calc(var(--avatar-size) * var(--avatar-overlap) - var(--avatar-size) / 2);
}

.avatar-group:not(.avatar-group-first-on-top) > :deep(*:not(:last-child)) {
  --_mask-x: calc(var(--avatar-size) * (1.5 - var(--avatar-overlap)));
}

.avatar-group-first-on-top > :deep(*:not(:first-child)),
.avatar-group:not(.avatar-group-first-on-top) > :deep(*:not(:last-child)) {
  -webkit-mask-image: radial-gradient(
    circle calc(var(--avatar-size) / 2 + var(--avatar-gap)) at var(--_mask-x) 50%,
    transparent 99%,
    #000 100%
  );
  mask-image: radial-gradient(
    circle calc(var(--avatar-size) / 2 + var(--avatar-gap)) at var(--_mask-x) 50%,
    transparent 99%,
    #000 100%
  );
}
</style>

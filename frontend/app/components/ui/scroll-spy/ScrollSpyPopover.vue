<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import ScrollSpyItems from "./ScrollSpyItems.vue";
import ScrollSpyProgressCircle from "./ScrollSpyProgressCircle.vue";
import ScrollSpyScrollArea from "./ScrollSpyScrollArea.vue";
import { useScrollSpy, type ScrollSpyHeading } from "./useScrollSpy";

const props = withDefaults(
  defineProps<{
    /** CSS selector of the container whose `h2`–`h6` become the entries. */
    contentSelector: string;
    /** Skip headings whose closest match this selector, e.g. `[role=tabpanel]`. */
    excludeSelector?: string;
    /** Shown while no heading is in view — usually the page title. */
    title?: string;
    class?: HTMLAttributes["class"];
  }>(),
  { excludeSelector: "", title: "On this page" },
);

const emit = defineEmits<{
  "headings-found": [headings: ScrollSpyHeading[]];
}>();

const {
  headings,
  activeIds,
  activeAnchorId,
  currentHeading,
  progress,
  scrollToHeading,
} = useScrollSpy({
  contentSelector: () => props.contentSelector,
  excludeSelector: () => props.excludeSelector,
  onHeadings: (found) => emit("headings-found", found),
});

const open = ref(false);
const headerRef = ref<HTMLElement | null>(null);

/** While collapsed, the trigger swaps the page title for the current heading. */
const showItem = computed(() => !open.value && currentHeading.value !== null);

function onSelect(id: string) {
  open.value = false;
  scrollToHeading(id);
}

function onWindowClick(event: MouseEvent) {
  if (!open.value) return;
  const target = event.target;
  if (!(target instanceof Node)) return;
  if (headerRef.value && !headerRef.value.contains(target)) open.value = false;
}

onMounted(() => window.addEventListener("click", onWindowClick));
onBeforeUnmount(() => window.removeEventListener("click", onWindowClick));
</script>

<template>
  <Collapsible
    v-show="headings.length > 0"
    v-model:open="open"
    data-slot="scroll-spy-popover"
    :class="
      cn(
        'sticky inset-x-0 top-(--navbar-height-mobile) z-50 h-10 xl:hidden',
        props.class,
      )
    "
  >
    <!-- Fixed-height sticky shell; this header overflows it while expanded. -->
    <header
      ref="headerRef"
      class="border-border/30 bg-background/95 supports-backdrop-filter:bg-background/90 border-b backdrop-blur-sm transition-shadow"
      :class="open && 'shadow-lg'"
    >
      <CollapsibleTrigger
        data-slot="scroll-spy-popover-trigger"
        class="text-muted-foreground flex h-10 w-full items-center gap-2.5 px-4 text-start text-sm focus-visible:outline-none md:px-6"
      >
        <ScrollSpyProgressCircle
          :value="progress"
          class="shrink-0 transition-colors"
          :class="open && 'text-primary'"
        />

        <span class="grid flex-1 *:col-start-1 *:row-start-1 *:my-auto">
          <span
            class="truncate transition-[opacity,translate,color]"
            :class="[
              open && 'text-foreground',
              showItem && 'pointer-events-none -translate-y-full opacity-0',
            ]"
          >
            {{ title }}
          </span>
          <span
            class="truncate transition-[opacity,translate]"
            :class="
              !showItem && 'pointer-events-none translate-y-full opacity-0'
            "
          >
            {{ currentHeading?.text }}
          </span>
        </span>

        <Icon
          name="lucide:chevron-down"
          class="mx-0.5 size-4 shrink-0 transition-transform"
          :class="open && 'rotate-180'"
        />
      </CollapsibleTrigger>

      <CollapsibleContent data-slot="scroll-spy-popover-content">
        <div class="flex max-h-[50vh] flex-col px-4 md:px-6">
          <ScrollSpyScrollArea>
            <ScrollSpyItems
              :headings="headings"
              :active-ids="activeIds"
              :anchor-id="activeAnchorId"
              @select="onSelect"
            />
          </ScrollSpyScrollArea>
        </div>
      </CollapsibleContent>
    </header>
  </Collapsible>
</template>

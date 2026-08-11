<script setup lang="ts">
/**
 * The framed preview card: a chrome row, a scrolling body, and optional edge
 * fades. Owns its own scroller so the pane around it does not add a second one.
 *
 * Expanding teleports the card to `<body>` rather than switching it to
 * `position: fixed`. Fixed would be captured by the panel's
 * `container-type: inline-size` and anchor to the panel box instead of the
 * viewport; moving the node out of the container sidesteps that entirely.
 * Teleport relocates the existing DOM nodes, so component state survives —
 * inner scroll position does not.
 */
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import {
  computed,
  inject,
  nextTick,
  onBeforeUnmount,
  ref,
  watch,
  type HTMLAttributes,
} from "vue";
import { PREVIEW_PANEL_CONTEXT, PREVIEW_PANEL_FALLBACK_SPLIT } from "./context";

const props = withDefaults(
  defineProps<{
    /** Drop the border, background and rounding — just the scroller. */
    bare?: boolean;
    /** Chrome-row label. Usually the public URL the preview stands in for. */
    title?: string;
    reloadable?: boolean;
    expandable?: boolean;
    /** Inner padding on the body. Off when the preview draws its own page. */
    padded?: boolean;
    /** Wrap the body in ClientOnly — for previews that hydrate badly. */
    clientOnly?: boolean;
    /** Gradient edge fades. Only legible on a surface that owns a background. */
    fade?: boolean;
    class?: HTMLAttributes["class"];
  }>(),
  { padded: false },
);

const ctx = inject(PREVIEW_PANEL_CONTEXT, null);
const split = computed(() => ctx?.split.value ?? PREVIEW_PANEL_FALLBACK_SPLIT);

const localReloadKey = ref(0);
const reloadKey = computed(() => ctx?.reloadKey.value ?? localReloadKey.value);

function reload() {
  if (ctx) {
    ctx.reload();
    return;
  }
  localReloadKey.value += 1;
}

/**
 * A `:key` bump on a wrapper is not enough: the slot's vnodes belong to the
 * consumer, which has not re-rendered, so Vue patches them instead of
 * recreating them. Dropping the subtree for one tick is the only thing that
 * reliably re-runs a preview's setup.
 */
const previewMounted = ref(true);

watch(reloadKey, async () => {
  previewMounted.value = false;
  await nextTick();
  previewMounted.value = true;
});

const expanded = ref(false);

function onKeydown(event: KeyboardEvent) {
  if (event.key === "Escape") {
    expanded.value = false;
  }
}

watch(expanded, (open) => {
  if (typeof window === "undefined") return;
  if (open) {
    window.addEventListener("keydown", onKeydown);
  } else {
    window.removeEventListener("keydown", onKeydown);
  }
});

onBeforeUnmount(() => {
  if (typeof window !== "undefined") {
    window.removeEventListener("keydown", onKeydown);
  }
});

const hasChrome = computed(
  () => Boolean(props.title) || props.reloadable || props.expandable,
);
</script>

<template>
  <Teleport to="body" :disabled="!expanded">
    <div
      v-if="expanded"
      class="bg-background/70 fixed inset-0 z-50 backdrop-blur-sm"
      @click="expanded = false"
    />

    <div
      data-slot="preview-panel-surface"
      :data-expanded="expanded || undefined"
      :style="{
        '--pv-surface': 'color-mix(in oklab, var(--color-muted) 40%, var(--color-background))',
      }"
      :class="
        cn(
          'relative flex min-h-0 min-w-0 flex-col overflow-hidden',
          props.bare
            ? 'flex-1'
            : 'bg-(--pv-surface) flex-1 rounded-xl border @5xl/preview:rounded-2xl',
          expanded && 'fixed inset-2 z-50 flex-none rounded-xl border shadow-2xl sm:inset-4',
          props.class,
        )
      "
    >
      <div
        v-if="hasChrome || $slots.toolbar"
        data-slot="preview-panel-chrome"
        class="flex h-10 flex-none items-center gap-2 border-b px-2.5"
      >
        <p
          v-if="props.title"
          class="text-muted-foreground min-w-0 flex-1 truncate text-xs tracking-tight"
        >
          {{ props.title }}
        </p>
        <div v-else class="min-w-0 flex-1" />

        <div class="flex flex-none items-center gap-0.5">
          <slot name="toolbar" />
          <Button
            v-if="props.reloadable"
            variant="ghost"
            size="iconSm"
            aria-label="Reload preview"
            v-tippy="'Reload preview'"
            @click="reload"
          >
            <Icon name="hugeicons:reload" class="size-4 shrink-0" />
          </Button>
          <Button
            v-if="props.expandable"
            variant="ghost"
            size="iconSm"
            :aria-label="expanded ? 'Collapse preview' : 'Expand preview'"
            v-tippy="expanded ? 'Collapse preview' : 'Expand preview'"
            @click="expanded = !expanded"
          >
            <Icon
              :name="expanded ? 'hugeicons:arrow-shrink-01' : 'hugeicons:arrow-expand-01'"
              class="size-4 shrink-0"
            />
          </Button>
        </div>
      </div>

      <div
        data-slot="preview-panel-body"
        :class="
          cn(
            'relative min-h-0 flex-1 overflow-y-auto',
            split.overscroll,
            split.stackClear,
            props.padded && 'p-4',
          )
        "
      >
        <ClientOnly v-if="props.clientOnly">
          <slot v-if="previewMounted" />
          <template #fallback>
            <slot name="fallback">
              <div class="bg-muted/60 size-full animate-pulse" />
            </slot>
          </template>
        </ClientOnly>
        <slot v-else-if="previewMounted" />
      </div>

      <!--
        Absolutely positioned gradients rather than `scroll-fade-b`: that utility
        drives its mask from `animation-timeline: scroll(self y)` with
        `animation-fill-mode: both`, so a scroller with zero range stays pinned at
        keyframe 0% and the fade never lifts — which is every pane on mobile and
        any preview shorter than its pane.
      -->
      <template v-if="props.fade && !props.bare">
        <div
          class="from-(--pv-surface) pointer-events-none absolute inset-x-0 z-10 h-6 bg-linear-to-b to-transparent"
          :class="hasChrome || $slots.toolbar ? 'top-10' : 'top-0'"
        />
        <div
          class="from-(--pv-surface) pointer-events-none absolute inset-x-0 bottom-0 z-10 h-6 bg-linear-to-t to-transparent"
        />
      </template>
    </div>
  </Teleport>
</template>

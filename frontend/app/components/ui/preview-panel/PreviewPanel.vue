<script setup lang="ts">
/**
 * Edit on the left, live preview on the right, each scrolling on its own.
 * Below the split threshold the two become tabs driven by a floating pill.
 *
 * The split is a **container query on the panel's own width**, not a viewport
 * breakpoint, because the admin sidebar is 18rem open and 3rem collapsed —
 * a 240px swing, wider than a Tailwind breakpoint step. A viewport query cannot
 * see it, so at 1440px the same page would be comfortable with the sidebar
 * collapsed and cramped with it open.
 *
 * By default (`mode="sticky"`) only the preview pins: the page and the edit
 * pane scroll together the way any page does, and the preview holds its place
 * beside them. See `previewPanelModeClasses`. `mode="fill"` is the alternative
 * where the parent hands down a definite height and both panes scroll inside
 * it.
 *
 * See `context.ts` for the two invariants this folder must keep: no viewport
 * units, no `position: fixed`.
 */
import { cn } from "@/lib/utils";
import { TabsRoot } from "reka-ui";
import {
  computed,
  nextTick,
  provide,
  ref,
  useSlots,
  watch,
  type HTMLAttributes,
} from "vue";
import PreviewPanelPane from "./PreviewPanelPane.vue";
import PreviewPanelSurface from "./PreviewPanelSurface.vue";
import PreviewPanelSwitcher from "./PreviewPanelSwitcher.vue";
import {
  PREVIEW_PANEL_CONTEXT,
  PREVIEW_PANEL_DEFAULTS,
  PREVIEW_PANEL_GROW,
  PREVIEW_PANEL_SPLIT,
  previewPanelModeClasses,
  type PreviewPanelMode,
  type PreviewPanelRatio,
  type PreviewPanelSide,
  type PreviewPanelTab,
  type PreviewPanelThreshold,
} from "./context";

const props = withDefaults(
  defineProps<{
    /** Controlled active pane. Meaningless once the panes are side by side. */
    tab?: PreviewPanelTab;
    defaultTab?: PreviewPanelTab;
    /** Panel width at which the panes split. See PREVIEW_PANEL_SPLIT. */
    threshold?: PreviewPanelThreshold;
    ratio?: PreviewPanelRatio;
    /** Which side the preview lands on once split. */
    side?: PreviewPanelSide;
    /** How the panel gets its height. See previewPanelModeClasses. */
    mode?: PreviewPanelMode;
    /**
     * Where the pinned preview starts, as a CSS length. Passed in rather than
     * read from a token so the component stays free of any app's navbar height —
     * e.g. `offset="calc(var(--navbar-height-desktop) + 1rem)"`.
     */
    offset?: string;
    /** Gap the pinned preview keeps clear of the bottom of the viewport. */
    insetBottom?: string;
    editLabel?: string;
    previewLabel?: string;
    /** Chrome-row label on the preview card. Usually the public URL. */
    previewTitle?: string;
    /** Frame the preview in a card. Off renders the slot bare in a scroller. */
    surface?: boolean;
    /** Inner padding on the preview body. Off when it draws its own page. */
    padded?: boolean;
    reloadable?: boolean;
    expandable?: boolean;
    fade?: boolean;
    /** For previews that hydrate badly (deep reka trees drift on useId). */
    clientOnlyPreview?: boolean;
    /** The page renders its own pane switcher somewhere else. */
    hideSwitcher?: boolean;
    class?: HTMLAttributes["class"];
  }>(),
  {
    defaultTab: PREVIEW_PANEL_DEFAULTS.defaultTab,
    threshold: PREVIEW_PANEL_DEFAULTS.threshold,
    ratio: PREVIEW_PANEL_DEFAULTS.ratio,
    side: PREVIEW_PANEL_DEFAULTS.side,
    mode: PREVIEW_PANEL_DEFAULTS.mode,
    offset: PREVIEW_PANEL_DEFAULTS.offset,
    insetBottom: PREVIEW_PANEL_DEFAULTS.insetBottom,
    editLabel: PREVIEW_PANEL_DEFAULTS.editLabel,
    previewLabel: PREVIEW_PANEL_DEFAULTS.previewLabel,
    surface: true,
    padded: false,
  },
);

const emit = defineEmits<{
  "update:tab": [PreviewPanelTab];
  reload: [];
}>();

const uncontrolledTab = ref<PreviewPanelTab>(props.defaultTab);
const activeTab = computed({
  get: () => props.tab ?? uncontrolledTab.value,
  set: (value: PreviewPanelTab) => {
    uncontrolledTab.value = value;
    emit("update:tab", value);
  },
});

const split = computed(() => PREVIEW_PANEL_SPLIT[props.threshold]);
const grow = computed(() => PREVIEW_PANEL_GROW[props.ratio]);

const slots = useSlots();
const isSticky = computed(() => props.mode === "sticky");

/**
 * The footer is a fixed-height bar pinned to the bottom of the viewport, so the
 * pinned preview has to stop short of it or the two overlap. `h-14` on the
 * footer is what makes 3.5rem a fact rather than a guess.
 */
const panelStyle = computed(() => ({
  "--pv-offset": props.offset,
  "--pv-inset-b": slots.footer
    ? `calc(${props.insetBottom} + 3.5rem)`
    : props.insetBottom,
}));

const reloadKey = ref(0);
function reload() {
  reloadKey.value += 1;
  emit("reload");
}

const rootEl = ref<HTMLElement | null>(null);
const panesEl = ref<HTMLElement | null>(null);

/**
 * Switching tabs while stacked used to jump: only one pane is rendered, the two
 * are nowhere near the same height, so swapping them changes the height of the
 * document and the browser clamps the scroll position to whatever the new one
 * allows. Landing the new pane at its own top is both predictable and the only
 * position that is guaranteed to exist in either pane.
 *
 * Split mode is left alone — both panes are on screen there and the tab value
 * changes nothing. `flex-direction` is read from the DOM rather than
 * recomputing the threshold in JS, so the container query stays the one source
 * of truth about which mode is live.
 */
watch(activeTab, async () => {
  if (!panesEl.value || !rootEl.value) return;
  if (getComputedStyle(panesEl.value).flexDirection !== "column") return;

  await nextTick();

  const root = rootEl.value;
  const offset = Number.parseFloat(getComputedStyle(root).scrollMarginTop) || 0;
  if (root.getBoundingClientRect().top >= offset) return;

  root.scrollIntoView({
    behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches
      ? "auto"
      : "smooth",
    block: "start",
  });
});

provide(PREVIEW_PANEL_CONTEXT, { split, reloadKey, reload });

defineExpose({ reload });
</script>

<template>
  <TabsRoot v-model="activeTab" as-child>
    <div
      ref="rootEl"
      data-slot="preview-panel"
      :data-mode="props.mode"
      :style="panelStyle"
      :class="
        cn(
          // scroll-margin is what the tab-change scroll lands against, so the
          // new pane starts below the page's sticky chrome rather than under it.
          '@container/preview relative flex w-full min-w-0 scroll-mt-(--pv-offset) flex-col',
          previewPanelModeClasses[props.mode],
          props.class,
        )
      "
    >
      <div
        ref="panesEl"
        data-slot="preview-panel-panes"
        :class="
          cn(
            // Gap is a step wider than it looks in fill mode: the edit pane
            // bleeds a 4px ring gutter into it (see PreviewPanelPane's `gutter`).
            'flex min-h-0 min-w-0 flex-1 flex-col gap-4 sm:gap-5',
            props.side === 'left' ? split.rowReverse : split.row,
            // Stretching would make the preview as tall as the edit pane, and a
            // sticky box with nowhere to travel never sticks.
            isSticky && split.alignStart,
          )
        "
      >
        <!-- In sticky mode the edit pane is plain page content: no scroller of
             its own, no frame, no ring gutter to compensate for. -->
        <PreviewPanelPane
          value="edit"
          :grow="grow.edit"
          :scroll="!isSticky"
          :gutter="!isSticky"
        >
          <slot name="edit" />
        </PreviewPanelPane>

        <PreviewPanelPane
          value="preview"
          :grow="grow.preview"
          :scroll="false"
          :class="isSticky && split.previewStick"
        >
          <PreviewPanelSurface
            :bare="!props.surface"
            :title="props.previewTitle"
            :reloadable="props.reloadable"
            :expandable="props.expandable"
            :padded="props.padded"
            :fade="props.fade"
            :client-only="props.clientOnlyPreview"
          >
            <slot name="preview" />
            <template v-if="$slots['preview-toolbar']" #toolbar>
              <slot name="preview-toolbar" />
            </template>
            <template v-if="$slots['preview-fallback']" #fallback>
              <slot name="preview-fallback" />
            </template>
          </PreviewPanelSurface>
        </PreviewPanelPane>
      </div>

      <!--
        Two elements on purpose. The pill is `fixed`, anchored like every other
        pane switcher in the app (`post/PostEditor.vue`) —
        `container-type: inline-size` does NOT make the panel a containing block
        for fixed descendants, measured rather than assumed.

        But that also takes the pill out of the panel's containing-block chain,
        and a container query resolves an out-of-flow element against THAT chain,
        not the DOM tree. Put `@5xl:invisible` on the pill itself and it simply
        never matches, leaving the switcher on screen next to a split that has
        no use for it. So the query lives on this static wrapper, which is still
        in the chain, and `visibility` — an inherited property — carries the
        result down to the fixed child. The wrapper adds no height of its own:
        its only child is out of flow.

        `invisible`, not `hidden`: reka's TabsIndicator measures the active
        trigger, and a `display:none` tablist measures zero, so collapsing the
        sidebar would animate a zero-width indicator out from the left.
      -->
      <div
        v-if="!props.hideSwitcher"
        data-slot="preview-panel-switcher-rail"
        :class="cn(split.switcherOff)"
      >
        <div class="fixed bottom-8 left-1/2 z-50 -translate-x-1/2">
          <slot name="switcher">
            <PreviewPanelSwitcher
              :items="[
                { value: 'edit', label: props.editLabel },
                { value: 'preview', label: props.previewLabel },
              ]"
            />
          </slot>
        </div>
      </div>

      <!--
        Panel-level, not per-pane: it spans both columns in the split, and it
        keeps the switcher rail above it in flow order (see above).

        Fixed `h-14` so the pinned preview can subtract a real number instead of
        guessing, and sticky so a long edit pane never scrolls Save off-screen.
      -->
      <div
        v-if="$slots.footer"
        data-slot="preview-panel-footer"
        :class="
          cn(
            'bg-background/85 flex h-14 flex-none items-center border-t backdrop-blur-sm',
            isSticky && 'sticky bottom-0 z-20',
          )
        "
      >
        <div class="w-full"><slot name="footer" /></div>
      </div>
    </div>
  </TabsRoot>
</template>

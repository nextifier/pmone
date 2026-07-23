<script setup lang="ts">
import {
  computed,
  inject,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from "vue";
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";
import {
  SCROLL_SPY_VIEWPORT,
  getItemOffset,
  getLineOffset,
  type ScrollSpyHeading,
} from "./useScrollSpy";

const props = defineProps<{
  headings: ScrollSpyHeading[];
  activeIds: string[];
  /** Heading that went active most recently — the one to keep scrolled into view. */
  anchorId?: string | null;
  class?: HTMLAttributes["class"];
}>();

const emit = defineEmits<{ select: [id: string] }>();

const viewport = inject(SCROLL_SPY_VIEWPORT, null);

const containerRef = ref<HTMLElement | null>(null);
/** Plain map on purpose — these are only read imperatively while measuring. */
const linkEls = new Map<string, HTMLElement>();

function setLinkRef(id: string, el: unknown) {
  if (el) linkEls.set(id, el as HTMLElement);
  else linkEls.delete(id);
}

const activeSet = computed(() => new Set(props.activeIds));

/**
 * Per-item rail geometry. Every link draws its own segment of the muted rail;
 * when the depth changes between two neighbours the segment bends across with a
 * bezier elbow, which is what gives the rail its "circuit" look.
 */
const railItems = computed(() =>
  props.headings.map((heading, index) => {
    const l1 = getLineOffset(heading.depth);
    const prev = props.headings[index - 1];
    const next = props.headings[index + 1];
    const l0 = prev ? getLineOffset(prev.depth) : l1;
    const l2 = next ? getLineOffset(next.depth) : l1;

    return {
      heading,
      isFirst: index === 0,
      isLast: index === props.headings.length - 1,
      padStart: getItemOffset(heading.depth),
      width: Math.max(l0, l1) + 9,
      // The segment stops 6px short so the next item's elbow can take over.
      taper: l1 !== l2,
      elbow:
        l0 === l1
          ? null
          : `M ${l0 + 0.5} 0 C ${l0 + 0.5} 8 ${l1 + 0.5} 4 ${l1 + 0.5} 12`,
      lineX: l1 + 0.5,
      lineY: l0 === l1 ? 6 : 12,
    };
  }),
);

interface Track {
  d: string;
  width: number;
  height: number;
  /** [top, bottom] of every link, indexed like `props.headings`. */
  positions: ([number, number] | null)[];
  /** Distance along `d` where every link starts and ends, same indexing. */
  lengths: ([number, number] | null)[];
}

const track = ref<Track | null>(null);

/**
 * Trace one continuous path through every link, matching the per-item rail
 * exactly. The highlighted portion is then carved out of it with a clip-path,
 * so the active range is drawn in the primary colour on top of the muted rail.
 */
function measure() {
  const container = containerRef.value;
  // Collapsed or off-screen: keep the last measurement instead of flashing the
  // rail away, the ResizeObserver re-runs this as soon as it has a size again.
  if (!container || container.clientHeight === 0) return;
  if (props.headings.length === 0) {
    track.value = null;
    return;
  }

  let width = 0;
  let height = 0;
  let d = "";
  let prev: { bottom: number; x: number } | null = null;
  const positions: ([number, number] | null)[] = [];

  props.headings.forEach((heading) => {
    const el = linkEls.get(heading.id);
    if (!el) {
      positions.push(null);
      return;
    }

    const styles = getComputedStyle(el);
    const x = getLineOffset(heading.depth) + 0.5;
    const top = el.offsetTop + parseFloat(styles.paddingTop);
    const bottom =
      el.offsetTop + el.clientHeight - parseFloat(styles.paddingBottom);

    width = Math.max(x + 8, width);
    height = Math.max(height, bottom);

    if (!prev) d += ` M${x} ${top} L${x} ${bottom}`;
    else
      d += ` C ${prev.x} ${top - 4} ${x} ${prev.bottom + 4} ${x} ${top} L${x} ${bottom}`;

    prev = { bottom, x };
    positions.push([top, bottom]);
  });

  if (!prev) {
    track.value = null;
    return;
  }

  track.value = {
    d,
    width,
    height,
    positions,
    lengths: measureLengths(d, positions),
  };
}

/**
 * The travelling dot is placed with `offset-distance`, which counts along the
 * path — and the elbows make the path longer than the vertical drop. So walk
 * the real path to find where each link starts and ends on it.
 */
function measureLengths(d: string, positions: ([number, number] | null)[]) {
  const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
  path.setAttribute("d", d);

  // Measuring a detached path is not universally supported; where it fails the
  // seed below is the plain vertical position, which is right for a rail with
  // no elbows and close enough for one with them.
  let total = 0;
  try {
    total = path.getTotalLength();
  } catch {
    total = 0;
  }

  const lengths: ([number, number] | null)[] = [];
  let previous: {
    position: [number, number];
    length: [number, number];
  } | null = null;

  for (const position of positions) {
    if (!position) {
      lengths.push(null);
      continue;
    }

    const [top, bottom] = position;
    let length = previous
      ? previous.length[1] + (top - previous.position[1])
      : top;
    try {
      while (length < total && path.getPointAtLength(length).y < top) length++;
    } catch {
      // Same fallback as above: keep the seeded vertical position.
    }

    const entry: [number, number] = [length, length + bottom - top];
    lengths.push(entry);
    previous = { position, length: entry };
  }

  return lengths;
}

/** Indices of the first and last visible heading, or -1 when none are. */
const activeRange = computed(() => ({
  start: props.headings.findIndex((heading) => activeSet.value.has(heading.id)),
  end: props.headings.findLastIndex((heading) =>
    activeSet.value.has(heading.id),
  ),
}));

// Reading downwards the dot rides the bottom edge of the highlight; reading
// back up it jumps to the top edge, so it always sits where the eye is heading.
let previousRange: { start: number; end: number; isUp: boolean } | null = null;
const isUp = ref(false);

watch(
  activeRange,
  ({ start, end }) => {
    if (start === -1) return;

    const previous = previousRange;
    isUp.value = previous
      ? previous.start > start ||
        previous.end > end ||
        (previous.start === start && previous.end === end && previous.isUp)
      : false;
    previousRange = { start, end, isUp: isUp.value };
  },
  { immediate: true },
);

const trackStyle = computed(() => {
  const current = track.value;
  if (!current) return undefined;

  const style: Record<string, string> = {
    width: `${current.width}px`,
    height: `${current.height}px`,
  };

  const { start, end } = activeRange.value;
  const from = start === -1 ? null : current.positions[start];
  const to = end === -1 ? null : current.positions[end];
  if (!from || !to) return style;

  style["--track-top"] = `${from[0]}px`;
  style["--track-bottom"] = `${to[1]}px`;

  const dot = isUp.value
    ? current.lengths[start]?.[0]
    : current.lengths[end]?.[1];
  if (dot !== undefined) {
    style["--offset-distance"] = `${dot}px`;
    style["--opacity"] = "1";
  }

  return style;
});

let resizeObserver: ResizeObserver | null = null;

onMounted(async () => {
  await nextTick();
  measure();

  resizeObserver = new ResizeObserver(() => measure());
  if (containerRef.value) resizeObserver.observe(containerRef.value);
});

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
  resizeObserver = null;
});

watch(
  () => props.headings,
  async () => {
    await nextTick();
    measure();
  },
);

// Keep the heading the reader just reached inside the (often scrollable) TOC.
let isInitialScroll = true;

watch(
  () => props.anchorId,
  (id) => {
    const container = viewport?.value;
    const el = id ? linkEls.get(id) : null;
    if (!container || !el) return;

    const containerRect = container.getBoundingClientRect();
    const elRect = el.getBoundingClientRect();
    const delta =
      elRect.top +
      elRect.height / 2 -
      (containerRect.top + containerRect.height / 2);

    if (Math.abs(delta) < 1) return;
    container.scrollBy({
      top: delta,
      behavior: isInitialScroll ? "instant" : "smooth",
    });
    isInitialScroll = false;
  },
);
</script>

<template>
  <div
    ref="containerRef"
    data-slot="scroll-spy-items"
    :class="cn('relative flex flex-col', props.class)"
  >
    <!-- Active range: the full rail redrawn in the primary colour, clipped to
         the span between the first and last visible heading. -->
    <div
      v-if="track"
      class="absolute start-0 top-0 origin-center rtl:-scale-x-100"
      :style="trackStyle"
      aria-hidden="true"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        :viewBox="`0 0 ${track.width} ${track.height}`"
        class="absolute transition-[clip-path]"
        :style="{
          width: `${track.width}px`,
          height: `${track.height}px`,
          clipPath:
            'polygon(0 var(--track-top,0), 100% var(--track-top,0), 100% var(--track-bottom,0), 0 var(--track-bottom,0))',
        }"
      >
        <path
          :d="track.d"
          class="stroke-primary"
          stroke-width="1"
          fill="none"
        />
      </svg>

      <div
        class="bg-primary absolute left-0 size-1 rounded-full opacity-(--opacity,0) transition-[opacity,offset-distance] [offset-distance:var(--offset-distance,0)]"
        :style="{ offsetPath: `path(&quot;${track.d}&quot;)` }"
      />
    </div>

    <a
      v-for="item in railItems"
      :key="item.heading.id"
      :ref="(el) => setLinkRef(item.heading.id, el)"
      :href="`#${item.heading.id}`"
      :data-active="activeSet.has(item.heading.id)"
      class="text-muted-foreground hover:text-foreground data-[active=true]:text-primary focus-visible:outline-ring relative scroll-m-4 rounded-md py-1.5 text-sm wrap-anywhere no-underline transition-colors focus-visible:ring-0 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-solid"
      :class="[item.isFirst && 'pt-0', item.isLast && 'pb-0']"
      :style="{ paddingInlineStart: `${item.padStart}px` }"
      @click.prevent="emit('select', item.heading.id)"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="absolute start-0 -top-1.5 bottom-0 -z-1 h-[calc(100%+--spacing(1.5))] rtl:-scale-x-100"
        :class="item.taper && 'bottom-1.5 h-full'"
        :style="{ width: `${item.width}px` }"
        aria-hidden="true"
      >
        <path
          v-if="item.elbow"
          :d="item.elbow"
          stroke-width="1"
          fill="none"
          class="stroke-foreground/10"
        />
        <line
          :x1="item.lineX"
          :y1="item.lineY"
          :x2="item.lineX"
          y2="100%"
          stroke-width="1"
          class="stroke-foreground/10"
        />
      </svg>
      {{ item.heading.text }}
    </a>
  </div>
</template>

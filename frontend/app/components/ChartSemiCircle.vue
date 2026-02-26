<template>
  <div ref="chartEl" class="@container flex w-full flex-col items-center">
    <div
      v-if="gradient"
      ref="probeEl"
      :class="['invisible absolute size-0 bg-linear-to-r', gradient]"
      aria-hidden="true"
    />
    <div class="relative w-full">
      <svg :viewBox="`0 0 ${svgWidth} ${svgHeight}`" class="w-full">
        <line
          v-for="(bar, i) in bars"
          :key="i"
          :x1="cx"
          :y1="cy - innerRadius"
          :x2="cx"
          :y2="cy - outerRadius"
          :transform="`rotate(${bar.angle}, ${cx}, ${cy})`"
          :stroke="bar.color"
          :stroke-width="barWidth"
          stroke-linecap="round"
          :style="
            animateBars
              ? `transition:stroke ${barTransitionMs}ms ease-out ${i * barStaggerMs}ms`
              : undefined
          "
        />
      </svg>

      <!-- Center content -->
      <div
        class="absolute inset-0 flex translate-y-0.5 flex-col items-center justify-end text-center"
      >
        <div class="flex items-baseline">
          <NumberFlow
            v-if="animateValue"
            class="text-foreground translate-y-1 text-[12cqw] font-medium tracking-tighter"
            :value="Number(displayValue) || 0"
            :format="{ notation: 'compact' }"
          />
          <span v-else class="text-foreground text-[12cqw] font-medium tracking-tighter">{{
            staticDisplayValue
          }}</span>
          <span v-if="showMax" class="text-muted-foreground text-[6cqw] tracking-tighter"
            >/{{ formattedMax }}</span
          >
          <span v-if="suffix" class="text-muted-foreground text-[5cqw] tracking-tighter">{{ suffix }}</span>
        </div>
        <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{
          centerLabel
        }}</span>
      </div>
    </div>
  </div>
</template>

<script>
// Each color stop: [position (0-1), L, C, H] in oklch
const RAINBOW_GRADIENT = [
  [0, 0.595, 0.196, 254.96],
  [0.07, 0.659, 0.187, 252.32],
  [0.25, 0.775, 0.121, 247.99],
  [0.49, 0.953, 0.083, 144.45],
  [0.75, 0.875, 0.175, 92.22],
  [0.95, 0.7, 0.202, 44.18],
  [1.0, 0.676, 0.217, 38.8],
];
</script>

<script setup>
const props = defineProps({
  value: { type: Number, required: true },
  max: { type: Number, required: true },
  centerValue: { type: [Number, String], default: null },
  centerLabel: { type: String, default: "" },
  showRemaining: { type: Boolean, default: false },
  showMax: { type: Boolean, default: false },
  colors: { type: Array, default: () => RAINBOW_GRADIENT },
  gradient: { type: String, default: null },
  totalBars: { type: Number, default: 40 },
  animateBars: { type: Boolean, default: true },
  animateValue: { type: Boolean, default: true },
  suffix: { type: String, default: "" },
  compact: { type: Boolean, default: true },
});

const svgWidth = 242;
const svgHeight = 124;
const cx = 121;
const cy = 121;
const innerRadius = 85;
const outerRadius = 118;
const barWidth = 4.5;

const chartEl = ref(null);
const probeEl = ref(null);
const isRevealed = ref(!props.animateBars);
const animatedCenterValue = ref(0);
const gradientBarColors = ref(null);

const remaining = computed(() => props.max - props.value);

const resolvedCenterValue = computed(() => {
  if (props.centerValue !== null) return props.centerValue;
  return props.showRemaining ? remaining.value : props.value;
});

function interpolateColor(t) {
  const stops = props.colors;

  if (t <= stops[0][0]) {
    const [, l, c, h] = stops[0];
    return `oklch(${l} ${c} ${h})`;
  }
  if (t >= stops[stops.length - 1][0]) {
    const [, l, c, h] = stops[stops.length - 1];
    return `oklch(${l} ${c} ${h})`;
  }

  let i = 0;
  while (i < stops.length - 1 && stops[i + 1][0] < t) i++;

  const [p1, l1, c1, h1] = stops[i];
  const [p2, l2, c2, h2] = stops[i + 1];
  const f = (t - p1) / (p2 - p1);

  const l = l1 + (l2 - l1) * f;
  const c = c1 + (c2 - c1) * f;
  const h = h1 + (h2 - h1) * f;
  return `oklch(${l.toFixed(3)} ${c.toFixed(3)} ${h.toFixed(1)})`;
}

function resolveGradient(el, count) {
  const bgImage = getComputedStyle(el).backgroundImage;
  if (!bgImage || bgImage === "none") return null;

  // Parse "linear-gradient(direction, stop1, stop2, ...)" splitting by top-level commas
  const inner = bgImage.slice(bgImage.indexOf("(") + 1, bgImage.lastIndexOf(")"));
  const parts = [];
  let depth = 0;
  let current = "";
  for (const ch of inner) {
    if (ch === "(") depth++;
    else if (ch === ")") depth--;
    if (ch === "," && depth === 0) {
      parts.push(current.trim());
      current = "";
    } else {
      current += ch;
    }
  }
  if (current.trim()) parts.push(current.trim());

  // First part is direction, rest are color stops
  const stops = parts.slice(1).map((s) => {
    const pctMatch = s.match(/([\d.]+)%\s*$/);
    return {
      color: pctMatch ? s.slice(0, pctMatch.index).trim() : s.trim(),
      pos: pctMatch ? parseFloat(pctMatch[1]) / 100 : null,
    };
  });

  if (stops.length < 2) return null;

  // Fill missing positions
  if (stops[0].pos === null) stops[0].pos = 0;
  if (stops[stops.length - 1].pos === null) stops[stops.length - 1].pos = 1;
  let last = 0;
  for (let i = 1; i < stops.length; i++) {
    if (stops[i].pos !== null) {
      for (let j = last + 1; j < i; j++) {
        stops[j].pos =
          stops[last].pos + ((stops[i].pos - stops[last].pos) * (j - last)) / (i - last);
      }
      last = i;
    }
  }

  // Sample colors via canvas
  const canvas = document.createElement("canvas");
  canvas.width = count;
  canvas.height = 1;
  const ctx = canvas.getContext("2d");
  const grad = ctx.createLinearGradient(0, 0, count - 1, 0);
  for (const { color, pos } of stops) {
    grad.addColorStop(pos, color);
  }
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, count, 1);

  const data = ctx.getImageData(0, 0, count, 1).data;
  return Array.from({ length: count }, (_, i) => {
    const o = i * 4;
    return `rgb(${data[o]},${data[o + 1]},${data[o + 2]})`;
  });
}

const barStaggerMs = 20;
const barTransitionMs = 500;

const fillRatio = computed(() => Math.min(props.value / props.max, 1));

const bars = computed(() => {
  const total = props.totalBars;
  const filledCount = Math.round(total * fillRatio.value);
  const precomputed = gradientBarColors.value;

  return Array.from({ length: total }, (_, i) => {
    const angle = -90 + (180 * i) / (total - 1);
    const shouldFill = isRevealed.value && i < filledCount;

    return {
      angle,
      color: shouldFill
        ? precomputed
          ? precomputed[i]
          : interpolateColor(i / (total - 1))
        : "var(--border)",
    };
  });
});

const compactFormatter = new Intl.NumberFormat(undefined, { notation: "compact" });
const plainFormatter = new Intl.NumberFormat();

const numberFormatter = computed(() => (props.compact ? compactFormatter : plainFormatter));

const displayValue = computed(() =>
  typeof resolvedCenterValue.value === "string"
    ? resolvedCenterValue.value
    : animatedCenterValue.value
);

const staticDisplayValue = computed(() => {
  const val = resolvedCenterValue.value;
  return typeof val === "string" ? val : numberFormatter.value.format(val);
});

const formattedMax = computed(() => numberFormatter.value.format(props.max));

let animationFrame = null;

function animateCenterValue() {
  const targetCenter =
    typeof resolvedCenterValue.value === "number" ? resolvedCenterValue.value : 0;

  if (!props.animateValue) {
    animatedCenterValue.value = targetCenter;
    return;
  }

  const duration = barStaggerMs * (props.totalBars - 1) + barTransitionMs;
  const start = performance.now();

  function step(now) {
    const elapsed = now - start;
    const t = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - t, 3);

    animatedCenterValue.value = Math.round(targetCenter * eased);

    if (t < 1) {
      animationFrame = requestAnimationFrame(step);
    }
  }

  animationFrame = requestAnimationFrame(step);
}

function reveal() {
  if (props.animateBars) isRevealed.value = true;
  animateCenterValue();
}

let observer = null;

onMounted(() => {
  if (props.gradient && probeEl.value) {
    gradientBarColors.value = resolveGradient(probeEl.value, props.totalBars);
  }

  if (!props.animateBars && !props.animateValue) {
    animatedCenterValue.value =
      typeof resolvedCenterValue.value === "number" ? resolvedCenterValue.value : 0;
    return;
  }

  observer = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting && !isRevealed.value) {
        reveal();
        observer?.disconnect();
      }
    },
    { rootMargin: "-80px 0px" }
  );

  if (chartEl.value) observer.observe(chartEl.value);
});

onUnmounted(() => {
  if (animationFrame) cancelAnimationFrame(animationFrame);
  observer?.disconnect();
});
</script>

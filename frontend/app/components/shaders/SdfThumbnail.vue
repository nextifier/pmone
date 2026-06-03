<script setup>
import { onMounted, ref, watch } from "vue";

/**
 * Renders a small preview of a stored SDF `.bin` by decoding the 512x512 signed
 * distance field and drawing the shape silhouette (sdf < 0 = inside) to a canvas.
 * A `.bin` is not a viewable image on its own, so we reconstruct the logo outline.
 * Edges use the distance for cheap anti-aliasing. Colour follows the theme foreground.
 */
const props = defineProps({
  url: { type: String, required: true },
  size: { type: Number, default: 80 },
});

const canvas = ref(null);
const state = ref("loading");

async function render() {
  state.value = "loading";
  const el = canvas.value;
  if (!el) {
    return;
  }
  try {
    const res = await fetch(props.url, { mode: "cors" });
    if (!res.ok) {
      throw new Error("fetch failed");
    }
    const u = new Uint16Array(await res.arrayBuffer());
    const N = 512;
    if (u.length < N * N) {
      throw new Error("not an sdf");
    }

    const S = props.size;
    el.width = S;
    el.height = S;
    const ctx = el.getContext("2d");

    // Resolve the theme foreground colour to rgb (handles any CSS colour format).
    ctx.fillStyle = getComputedStyle(el).color || "#0a0a0a";
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data;
    ctx.clearRect(0, 0, S, S);

    const img = ctx.createImageData(S, S);
    const step = N / S;
    for (let y = 0; y < S; y++) {
      for (let x = 0; x < S; x++) {
        const sx = Math.min(N - 1, Math.floor((x + 0.5) * step));
        const sy = Math.min(N - 1, Math.floor((y + 0.5) * step));
        const v = u[sy * N + sx] / 32767.5 - 1; // < 0 inside the shape
        const alpha = Math.max(0, Math.min(1, 0.5 - v * S));
        const o = (y * S + x) * 4;
        img.data[o] = r;
        img.data[o + 1] = g;
        img.data[o + 2] = b;
        img.data[o + 3] = Math.round(alpha * 255);
      }
    }
    ctx.putImageData(img, 0, 0);
    state.value = "ready";
  } catch {
    state.value = "error";
  }
}

onMounted(render);
watch(() => props.url, render);
</script>

<template>
  <div class="outline-inside flex size-12 shrink-0 items-center justify-center rounded-lg bg-white">
    <canvas v-show="state === 'ready'" ref="canvas" class="text-foreground size-full" />
    <Icon
      v-if="state !== 'ready'"
      :name="state === 'error' ? 'hugeicons:image-not-found-01' : 'hugeicons:loading-03'"
      :class="['text-muted-foreground size-4', state === 'loading' && 'animate-spin']"
    />
  </div>
</template>

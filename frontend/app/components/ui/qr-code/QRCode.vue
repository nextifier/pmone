<template>
  <!-- aspect-square reserves the space before the QR generates (no layout shift) -->
  <div :class="cn('aspect-square w-full', props.class)">
    <template v-if="svgContent">
      <Tippy v-if="canToggle" tag="div" theme="primary" placement="bottom">
        <button
          type="button"
          @click="toggleQrStyle"
          class="block w-full cursor-pointer rounded-lg transition active:scale-98"
        >
          <div v-html="svgContent" class="[&>svg]:block [&>svg]:h-auto [&>svg]:w-full" />
        </button>
        <template #content>
          <span class="flex items-center gap-x-1.5 leading-none tracking-tight">
            <Icon name="hugeicons:mouse-left-click-01" class="size-4 shrink-0" />
            <span>Click to change QR Code style</span>
          </span>
        </template>
      </Tippy>
      <div
        v-else
        v-html="svgContent"
        class="[&>svg]:block [&>svg]:h-auto [&>svg]:w-full"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, shallowRef, watch } from "vue";
import type { HTMLAttributes } from "vue";
import type QRCodeLib from "qrcode";
import { cn } from "@/lib/utils";
import {
  buildQRSvgString,
  loadQRCodeLib,
  useQRCodeStyle,
  type QRStyleVariant,
} from "./useQRCode";

const props = withDefaults(
  defineProps<{
    /** Text encoded in the QR. Any string: URL, token, plain text. */
    url: string;
    /** Rendered SVG viewBox size. The element itself always fills its container. */
    size?: number;
    /** Quiet zone around the code, in modules. */
    margin?: number;
    fgColor?: string;
    bgColor?: string;
    errorCorrectionLevel?: "L" | "M" | "Q" | "H";
    /** Pin the module shape. Left unset, it follows the shared, persisted style. */
    variant?: QRStyleVariant;
    /** Allow clicking the code to switch between square and rounded modules. */
    toggleable?: boolean;
    class?: HTMLAttributes["class"];
  }>(),
  {
    size: 268,
    margin: 2,
    fgColor: "var(--foreground)",
    bgColor: "transparent",
    errorCorrectionLevel: "M",
    variant: undefined,
    toggleable: true,
    class: undefined,
  },
);

const { qrStyle, toggleQrStyle } = useQRCodeStyle();

/** A pinned `variant` wins over the shared style, and drops the toggle with it. */
const activeVariant = computed<QRStyleVariant>(
  () => props.variant ?? qrStyle.value,
);
const canToggle = computed(() => props.toggleable && !props.variant);

const qrData = shallowRef<QRCodeLib.QRCode | null>(null);

const svgContent = computed(() => {
  if (!qrData.value) return "";

  const svg = buildQRSvgString(qrData.value, {
    size: props.size,
    margin: props.margin,
    fgColor: props.fgColor,
    bgColor: props.bgColor,
    styleVariant: activeVariant.value,
  });

  return svg
    .replace(`width="${props.size}"`, 'width="100%"')
    .replace(`height="${props.size}"`, 'height="100%"');
});

const generateQRData = async (): Promise<void> => {
  if (!import.meta.client || !props.url) {
    qrData.value = null;
    return;
  }

  try {
    const lib = await loadQRCodeLib();
    qrData.value = lib.create(props.url, {
      errorCorrectionLevel: props.errorCorrectionLevel,
    });
  } catch (err) {
    console.error("Failed to generate QR code:", err);
    qrData.value = null;
  }
};

onMounted(() => props.url && nextTick(generateQRData));

watch(
  () => [props.url, props.errorCorrectionLevel],
  () => props.url && nextTick(generateQRData),
);
</script>

<template>
  <Tippy v-if="svgContent" theme="primary" placement="bottom">
    <button
      type="button"
      @click="toggleQrStyle"
      class="block w-full cursor-pointer rounded-lg transition active:scale-98"
    >
      <div v-html="svgContent" />
    </button>
    <template #content>
      <span class="flex items-center gap-x-1.5 leading-none tracking-tight">
        <Icon name="hugeicons:mouse-left-click-01" class="size-4 shrink-0" />
        <span>Click to change QR Code style</span>
      </span>
    </template>
  </Tippy>
</template>

<script setup>
import { buildQRSvgString, useQRCodeStyle } from "@/composables/useQRCode";

const props = defineProps({
  url: {
    type: String,
    required: true,
  },
  size: {
    type: Number,
    default: 268,
  },
  margin: {
    type: Number,
    default: 2,
  },
  fgColor: {
    type: String,
    default: "var(--foreground)",
  },
  bgColor: {
    type: String,
    default: "var(--background)",
  },
  errorCorrectionLevel: {
    type: String,
    default: "M",
  },
});

const { qrStyle, toggleQrStyle } = useQRCodeStyle();

const qrData = shallowRef(null);
const QRLib = shallowRef(null);

const svgContent = computed(() => {
  if (!qrData.value) return "";

  const svg = buildQRSvgString(qrData.value, {
    size: props.size,
    margin: props.margin,
    fgColor: props.fgColor,
    bgColor: props.bgColor,
    styleVariant: qrStyle.value,
  });

  return svg
    .replace(`width="${props.size}"`, 'width="100%"')
    .replace(`height="${props.size}"`, 'height="100%"');
});

const generateQRData = async () => {
  if (!import.meta.client || !props.url) {
    qrData.value = null;
    return;
  }

  try {
    if (!QRLib.value) {
      const mod = await import("qrcode");
      QRLib.value = mod.default;
    }
    qrData.value = QRLib.value.create(props.url, {
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
  () => props.url && nextTick(generateQRData)
);
</script>

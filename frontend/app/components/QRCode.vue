<template>
  <div v-if="svgContent" v-html="svgContent" />
</template>

<script setup>
import { buildQRSvgString } from "@/composables/useQRCode";

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

const qrData = shallowRef(null);
const QRLib = shallowRef(null);

const svgContent = computed(() => {
  if (!qrData.value) return "";

  const svg = buildQRSvgString(qrData.value, {
    size: props.size,
    margin: props.margin,
    fgColor: props.fgColor,
    bgColor: props.bgColor,
  });

  // Replace fixed width/height with 100% so it fills the container
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

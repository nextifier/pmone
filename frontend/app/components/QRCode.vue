<template>
  <div
    class="relative isolate before:absolute before:-inset-1.5 before:z-[-1] before:rounded-sm before:bg-white"
  >
    <canvas ref="qrcodeCanvas" :class="canvasClass" />
  </div>
</template>

<script setup>
const props = defineProps({
  url: {
    type: String,
    required: true,
  },
  canvasClass: {
    type: String,
    default: "size-24",
  },
  margin: {
    type: Number,
    default: 0,
  },
});

const qrcodeCanvas = ref(null);

const generateQRCode = async () => {
  if (!import.meta.client || !qrcodeCanvas.value || !props.url) return;

  try {
    const QRCode = await import("qrcode");
    const canvasSize = qrcodeCanvas.value.clientWidth || 96;

    qrcodeCanvas.value.width = canvasSize;
    qrcodeCanvas.value.height = canvasSize;

    await QRCode.toCanvas(qrcodeCanvas.value, props.url, {
      width: canvasSize,
      margin: props.margin,
      color: {
        dark: "#000000",
        light: "#FFFFFF",
      },
    });
  } catch (err) {
    console.error("Failed to generate QR code:", err);
  }
};

onMounted(() => props.url && nextTick(generateQRCode));

watch(
  () => props.url,
  (newUrl) => newUrl && nextTick(generateQRCode)
);
</script>

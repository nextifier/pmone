<template>
  <div class="min-h-screen-offset mx-auto flex flex-col gap-8 px-4 py-8 sm:px-6 lg:max-w-2xl">
    <div class="flex flex-col gap-2 text-center">
      <h1 class="text-3xl font-semibold tracking-tighter sm:text-4xl">QR Code Generator</h1>
      <p class="text-muted-foreground text-base tracking-tight">
        Generate high-quality QR codes instantly
      </p>
    </div>

    <div class="flex flex-col gap-8">
      <div class="flex flex-col gap-3">
        <Label for="qr-text" class="text-sm font-medium">Text or URL</Label>
        <Textarea
          id="qr-text"
          v-model="qrText"
          placeholder="Enter text or URL to generate QR code"
          rows="3"
          class="resize-none text-lg lg:text-xl"
        />
      </div>

      <div class="flex items-center justify-center">
        <div
          v-if="qrDataUrl"
          class="xs:max-w-[280px] aspect-square w-full overflow-hidden rounded-lg bg-white shadow-lg"
        >
          <img :src="qrDataUrl" alt="QR Code" class="size-full object-contain" />
        </div>
        <div
          v-else
          class="text-muted-foreground xs:max-w-[280px] flex aspect-square w-full flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed"
        >
          <Icon name="hugeicons:qr-code" class="size-12 opacity-50" />
          <p class="text-sm">QR code will appear here</p>
        </div>
      </div>

      <div v-if="qrDataUrl" class="flex flex-wrap justify-center gap-x-2 gap-y-4">
        <button
          @click="downloadJPG"
          class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center gap-x-1.5 rounded-lg px-4 py-2 font-medium tracking-tight transition active:scale-98"
        >
          <Icon name="hugeicons:jpg-01" class="size-5 shrink-0" />
          <span>Download JPG</span>
        </button>
        <button
          @click="downloadSVG"
          class="bg-muted text-foreground hover:bg-border flex items-center justify-center gap-x-1.5 rounded-lg px-4 py-2 font-medium tracking-tight transition active:scale-98"
        >
          <Icon name="hugeicons:svg-01" class="size-5 shrink-0" />
          <span>Download SVG</span>
        </button>

        <button
          @click="qrText = ''"
          class="border-border text-foreground hover:bg-muted flex items-center justify-center gap-x-1 rounded-lg border px-4 py-2 font-medium tracking-tight transition active:scale-98"
        >
          <Icon name="hugeicons:cancel-01" class="size-4.5 shrink-0" />
          <span>Clear</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import QRCode from "qrcode";

definePageMeta({
  layout: "default",
});

usePageMeta(null, {
  title: "Generate QR Code",
});

const qrText = ref("");
const qrDataUrl = ref("");

const previewSize = 300;
const downloadSize = 1080;
const errorCorrectionLevel = "H";
const qrMargin = 2;

const getCleanFilename = (text) => {
  return (
    text
      .replace(/^https?:\/\//, "")
      .replace(/^www\./, "")
      .replace(/[^a-zA-Z0-9.-]/g, "-")
      .replace(/-+/g, "-")
      .replace(/^-|-$/g, "")
      .substring(0, 50) || "qrcode"
  );
};

watch(
  qrText,
  async () => {
    if (!qrText.value) {
      qrDataUrl.value = "";
      return;
    }

    try {
      qrDataUrl.value = await QRCode.toDataURL(qrText.value, {
        width: previewSize,
        margin: qrMargin,
        errorCorrectionLevel: errorCorrectionLevel,
        color: {
          dark: "#000000",
          light: "#FFFFFF",
        },
      });
    } catch (err) {
      console.error("Error generating QR code:", err);
      qrDataUrl.value = "";
    }
  },
  { immediate: true }
);

const downloadJPG = async () => {
  if (!qrText.value) return;

  try {
    const dataUrl = await QRCode.toDataURL(qrText.value, {
      width: downloadSize,
      margin: qrMargin,
      errorCorrectionLevel: errorCorrectionLevel,
      color: {
        dark: "#000000",
        light: "#FFFFFF",
      },
    });

    const link = document.createElement("a");
    link.download = `QR-${getCleanFilename(qrText.value)}.png`;
    link.href = dataUrl;
    link.click();
  } catch (err) {
    console.error("Error generating QR code for download:", err);
  }
};

const downloadSVG = async () => {
  if (!qrText.value) return;

  try {
    const svgString = await QRCode.toString(qrText.value, {
      type: "svg",
      width: 512,
      margin: qrMargin,
      errorCorrectionLevel: errorCorrectionLevel,
      color: {
        dark: "#000000",
        light: "#FFFFFF",
      },
    });

    const blob = new Blob([svgString], { type: "image/svg+xml" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.download = `QR-${getCleanFilename(qrText.value)}.svg`;
    link.href = url;
    link.click();
    URL.revokeObjectURL(url);
  } catch (err) {
    console.error("Error generating SVG:", err);
  }
};
</script>

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
        <ClientOnly>
          <QRCode v-if="qrText" :url="qrText" class="xs:max-w-[280px] w-full" />
          <div
            v-else
            class="text-muted-foreground xs:max-w-[280px] flex aspect-square w-full flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed"
          >
            <Icon name="hugeicons:qr-code" class="size-12 opacity-50" />
            <p class="text-sm">QR code will appear here</p>
          </div>
        </ClientOnly>
      </div>

      <div v-if="qrText" class="flex flex-wrap justify-center gap-x-2 gap-y-4">
        <button
          @click="handleDownloadJPG"
          class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center gap-x-1.5 rounded-lg px-4 py-2 font-medium tracking-tight transition active:scale-98"
        >
          <Icon name="hugeicons:jpg-01" class="size-5 shrink-0" />
          <span>Download JPG</span>
        </button>
        <button
          @click="handleDownloadSVG"
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
import QRCode from "@/components/QRCode.vue";
import { useQRCode } from "@/composables/useQRCode";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta(null, {
  title: "Generate QR Code",
});

const qrText = ref("");

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

const { downloadSVG, downloadJPG } = useQRCode();

const handleDownloadJPG = async () => {
  if (!qrText.value) return;
  try {
    await downloadJPG(qrText.value, `QR-${getCleanFilename(qrText.value)}.png`);
  } catch (err) {
    console.error("Error generating QR code for download:", err);
  }
};

const handleDownloadSVG = async () => {
  if (!qrText.value) return;
  try {
    await downloadSVG(qrText.value, `QR-${getCleanFilename(qrText.value)}.svg`);
  } catch (err) {
    console.error("Error generating SVG:", err);
  }
};
</script>

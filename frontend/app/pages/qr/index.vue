<template>
  <div
    class="min-h-screen-offset mx-auto flex flex-col gap-6 pt-4 pb-4 lg:max-w-4xl xl:max-w-6xl"
  >
    <div class="flex flex-col gap-y-1">
      <h2 class="page-title">QR Code Generator</h2>
      <p class="page-description">
        Generate QR codes instantly and download them in high quality
      </p>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Input Section -->
      <div class="flex flex-col gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Input</CardTitle>
            <CardDescription>
              Enter text or URL to generate QR code
            </CardDescription>
          </CardHeader>
          <CardContent class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
              <Label for="qr-text">Text or URL</Label>
              <Textarea
                id="qr-text"
                v-model="qrText"
                placeholder="Enter text or URL..."
                rows="4"
                class="resize-none"
              />
            </div>

            <div class="flex flex-col gap-2">
              <Label for="qr-size">Size (pixels)</Label>
              <Input
                id="qr-size"
                v-model.number="qrSize"
                type="number"
                min="128"
                max="2048"
                step="64"
              />
            </div>

            <div class="flex flex-col gap-2">
              <Label for="qr-error">Error Correction Level</Label>
              <Select v-model="errorCorrectionLevel">
                <SelectTrigger id="qr-error">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="L">Low (~7%)</SelectItem>
                  <SelectItem value="M">Medium (~15%)</SelectItem>
                  <SelectItem value="Q">Quartile (~25%)</SelectItem>
                  <SelectItem value="H">High (~30%)</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex flex-col gap-2">
              <Label for="qr-margin">Margin (modules)</Label>
              <Input
                id="qr-margin"
                v-model.number="qrMargin"
                type="number"
                min="0"
                max="10"
              />
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Preview Section -->
      <div class="flex flex-col gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Preview</CardTitle>
            <CardDescription>
              {{ qrText ? "QR code preview" : "Enter text to see preview" }}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div
              v-if="qrText"
              class="bg-muted flex items-center justify-center rounded-lg p-6"
            >
              <canvas
                ref="qrCanvas"
                class="max-w-full rounded-md bg-white shadow-md"
              />
            </div>
            <div
              v-else
              class="bg-muted text-muted-foreground flex h-64 items-center justify-center rounded-lg"
            >
              <p class="text-sm">QR code will appear here</p>
            </div>
          </CardContent>
          <CardFooter v-if="qrText" class="flex flex-col gap-3">
            <Button @click="downloadJPG" class="w-full" size="lg">
              <Icon name="lucide:download" class="mr-2 h-4 w-4" />
              Download JPG
            </Button>
            <Button
              @click="downloadSVG"
              variant="outline"
              class="w-full"
              size="lg"
            >
              <Icon name="lucide:download" class="mr-2 h-4 w-4" />
              Download SVG
            </Button>
          </CardFooter>
        </Card>
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
const qrSize = ref(512);
const errorCorrectionLevel = ref("M");
const qrMargin = ref(4);
const qrCanvas = ref(null);

// Generate QR code whenever inputs change
watch(
  [qrText, qrSize, errorCorrectionLevel, qrMargin],
  async () => {
    if (!qrText.value || !qrCanvas.value) return;

    try {
      await QRCode.toCanvas(qrCanvas.value, qrText.value, {
        width: qrSize.value,
        margin: qrMargin.value,
        errorCorrectionLevel: errorCorrectionLevel.value,
        color: {
          dark: "#000000",
          light: "#FFFFFF",
        },
      });
    } catch (err) {
      console.error("Error generating QR code:", err);
    }
  },
  { immediate: true }
);

// Download as JPG
const downloadJPG = () => {
  if (!qrCanvas.value) return;

  // Create a temporary canvas with white background for JPG
  const tempCanvas = document.createElement("canvas");
  tempCanvas.width = qrCanvas.value.width;
  tempCanvas.height = qrCanvas.value.height;
  const ctx = tempCanvas.getContext("2d");

  // Fill white background
  ctx.fillStyle = "#FFFFFF";
  ctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);

  // Draw QR code on top
  ctx.drawImage(qrCanvas.value, 0, 0);

  // Download
  tempCanvas.toBlob(
    (blob) => {
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.download = `qrcode-${Date.now()}.jpg`;
      link.href = url;
      link.click();
      URL.revokeObjectURL(url);
    },
    "image/jpeg",
    1.0
  );
};

// Download as SVG
const downloadSVG = async () => {
  if (!qrText.value) return;

  try {
    const svgString = await QRCode.toString(qrText.value, {
      type: "svg",
      width: qrSize.value,
      margin: qrMargin.value,
      errorCorrectionLevel: errorCorrectionLevel.value,
      color: {
        dark: "#000000",
        light: "#FFFFFF",
      },
    });

    const blob = new Blob([svgString], { type: "image/svg+xml" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.download = `qrcode-${Date.now()}.svg`;
    link.href = url;
    link.click();
    URL.revokeObjectURL(url);
  } catch (err) {
    console.error("Error generating SVG:", err);
  }
};
</script>

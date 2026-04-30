<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-2xl">
    <div class="flex flex-col gap-2 text-center">
      <h1 class="text-3xl font-semibold tracking-tighter sm:text-4xl">Bluetooth Printer Test</h1>
      <p class="text-muted-foreground text-base tracking-tight">
        Test koneksi Web Bluetooth ke printer · Clabel CT221B (50x50mm)
      </p>
    </div>

    <ClientOnly>
      <div
        v-if="!isSupported"
        class="bg-warning/10 border-warning/30 text-warning-foreground flex items-start gap-3 rounded-lg border p-4 text-sm tracking-tight"
      >
        <Icon name="hugeicons:alert-02" class="size-5 shrink-0" />
        <div class="flex flex-col gap-1">
          <span class="font-medium">Web Bluetooth tidak didukung browser ini</span>
          <span>Gunakan Chrome, Edge, atau Opera di desktop. Safari belum support.</span>
        </div>
      </div>
    </ClientOnly>

    <div class="flex flex-col gap-6">
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <Label for="visitor-name" class="text-sm font-medium">Nama Visitor</Label>
          <Input
            id="visitor-name"
            v-model="visitorName"
            placeholder="Mis. Budi Santoso"
            maxlength="40"
          />
        </div>
        <div class="flex flex-col gap-2">
          <Label for="qr-data" class="text-sm font-medium">QR Code Data</Label>
          <Input id="qr-data" v-model="qrData" placeholder="https://pmone.id/v/abc123" />
        </div>
      </div>

      <div class="flex flex-col items-center gap-3">
        <Label class="text-muted-foreground text-sm">Preview (50×50mm @ 8 dots/mm)</Label>
        <div
          class="bg-muted/40 flex aspect-square w-full max-w-xs items-center justify-center rounded-lg border"
        >
          <ClientOnly>
            <canvas ref="previewCanvas" class="size-full rounded-md bg-white" />
          </ClientOnly>
        </div>
      </div>
    </div>

    <div class="border-border flex flex-col gap-4 rounded-lg border p-5">
      <div class="flex items-start justify-between gap-3">
        <div class="flex flex-col gap-1">
          <h2 class="text-lg font-semibold tracking-tight">Koneksi</h2>
          <ClientOnly>
            <p class="text-muted-foreground text-sm tracking-tight">
              <span v-if="status === 'disconnected' && !savedDeviceName">Belum terhubung</span>
              <span v-else-if="status === 'disconnected' && savedDeviceName">
                Device sebelumnya: {{ savedDeviceName }} · belum aktif
              </span>
              <span v-else-if="status === 'connecting'">Menghubungkan...</span>
              <span v-else-if="status === 'connected'" class="text-success">
                Terhubung: {{ device?.name ?? "(unnamed)" }}
              </span>
              <span v-else-if="status === 'error'" class="text-destructive">
                Error: {{ errorMessage }}
              </span>
              <span v-else-if="status === 'unsupported'" class="text-warning">
                Browser tidak support
              </span>
            </p>
          </ClientOnly>
        </div>
        <ClientOnly>
          <span
            class="inline-flex h-2.5 w-2.5 shrink-0 rounded-full"
            :class="{
              'bg-muted-foreground': status === 'disconnected',
              'bg-warning animate-pulse': status === 'connecting',
              'bg-success': status === 'connected',
              'bg-destructive': status === 'error',
              'bg-warning': status === 'unsupported',
            }"
          />
        </ClientOnly>
      </div>

      <div class="flex flex-wrap gap-2">
        <ClientOnly>
          <template v-if="status === 'connected'">
            <button
              @click="disconnect(false)"
              class="bg-muted text-foreground hover:bg-border flex items-center justify-center gap-x-1.5 rounded-lg px-4 py-2 font-medium tracking-tight transition active:scale-98"
            >
              <Icon name="hugeicons:wifi-disconnected-01" class="size-5 shrink-0" />
              <span>Disconnect</span>
            </button>
          </template>
          <template v-else-if="savedDeviceName">
            <button
              @click="handleReconnect"
              :disabled="status === 'connecting' || !isSupported"
              class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center gap-x-1.5 rounded-lg px-4 py-2 font-medium tracking-tight transition active:scale-98 disabled:opacity-50"
            >
              <Icon name="hugeicons:bluetooth" class="size-5 shrink-0" />
              <span>Reconnect ke {{ savedDeviceName }}</span>
            </button>
            <button
              @click="handleConnect"
              :disabled="status === 'connecting' || !isSupported"
              class="border-border hover:bg-muted flex items-center justify-center gap-x-1.5 rounded-lg border px-4 py-2 font-medium tracking-tight transition active:scale-98 disabled:opacity-50"
            >
              <Icon name="hugeicons:exchange-01" class="size-5 shrink-0" />
              <span>Pilih Device Lain</span>
            </button>
            <button
              @click="disconnect(true)"
              class="text-muted-foreground hover:bg-muted flex items-center justify-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight transition active:scale-98"
            >
              <Icon name="lucide:x" class="size-4 shrink-0" />
              <span>Lupakan</span>
            </button>
          </template>
          <template v-else>
            <button
              @click="handleConnect"
              :disabled="status === 'connecting' || !isSupported"
              class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center gap-x-1.5 rounded-lg px-4 py-2 font-medium tracking-tight transition active:scale-98 disabled:opacity-50"
            >
              <Icon name="hugeicons:bluetooth" class="size-5 shrink-0" />
              <span>Connect Printer</span>
            </button>
          </template>
        </ClientOnly>
      </div>
    </div>

    <div class="border-border flex flex-col gap-4 rounded-lg border p-5">
      <div class="flex flex-col gap-1">
        <h2 class="text-lg font-semibold tracking-tight">Print Test</h2>
        <p class="text-muted-foreground text-sm tracking-tight">
          Clabel CT221B pakai protokol TSPL. Native QR = printer render QR (ringan). Bitmap = kirim
          raster image (visual identik dengan preview).
        </p>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <button
          @click="handlePrint('tspl-native')"
          :disabled="!canPrint || printing"
          class="border-border hover:bg-muted flex flex-col items-start gap-1 rounded-lg border p-4 text-left tracking-tight transition active:scale-98 disabled:opacity-50"
        >
          <span class="flex items-center gap-2">
            <Icon name="hugeicons:tag-01" class="size-4 shrink-0" />
            <span class="font-medium">TSPL · Native QR</span>
          </span>
          <span class="text-muted-foreground text-xs sm:text-sm">
            QR di-render printer · ringan, tajam
          </span>
        </button>

        <!-- <button
          @click="handlePrint('tspl-bitmap')"
          :disabled="!canPrint || printing"
          class="border-border hover:bg-muted flex flex-col items-start gap-1 rounded-lg border p-4 text-left tracking-tight transition active:scale-98 disabled:opacity-50"
        >
          <span class="flex items-center gap-2">
            <Icon name="hugeicons:image-01" class="size-4 shrink-0" />
            <span class="font-medium">TSPL · Bitmap</span>
          </span>
          <span class="text-muted-foreground text-xs sm:text-sm">
            Kirim raster image · paling kompatibel
          </span>
        </button> -->
      </div>
    </div>

    <ClientOnly>
      <div
        v-if="discoveredChars.length > 0"
        class="border-border flex flex-col gap-3 rounded-lg border p-5"
      >
        <div class="flex flex-col gap-1">
          <h2 class="text-lg font-semibold tracking-tight">Writable Characteristics</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Kalau auto-pick salah, klik untuk override secara manual.
          </p>
        </div>
        <div class="flex flex-col gap-2">
          <button
            v-for="(char, idx) in discoveredChars"
            :key="`${char.serviceUuid}-${char.characteristicUuid}-${idx}`"
            @click="selectCharacteristic(char.serviceUuid, char.characteristicUuid)"
            class="border-border hover:bg-muted flex flex-col items-start gap-1 rounded-lg border p-3 text-left tracking-tight transition"
          >
            <span class="font-mono text-xs sm:text-sm">{{ char.characteristicUuid }}</span>
            <span class="text-muted-foreground font-mono text-xs">
              service: {{ char.serviceUuid }}
            </span>
            <span class="text-muted-foreground text-xs sm:text-sm">
              [{{ char.properties.join(", ") }}]
            </span>
          </button>
        </div>
      </div>
    </ClientOnly>

    <div class="border-border flex flex-col gap-3 rounded-lg border p-5">
      <div class="flex items-start justify-between gap-3">
        <div class="flex flex-col gap-1">
          <h2 class="text-lg font-semibold tracking-tight">Diagnostic Log</h2>
          <ClientOnly>
            <p class="text-muted-foreground text-sm tracking-tight">{{ logs.length }} entries</p>
          </ClientOnly>
        </div>
        <div class="flex gap-2">
          <button
            @click="copyLogs"
            class="border-border hover:bg-muted flex items-center gap-1 rounded-md border px-3 py-1.5 text-xs font-medium tracking-tight transition active:scale-98 sm:text-sm"
          >
            <Icon name="hugeicons:copy-01" class="size-3.5 shrink-0" />
            <span>Copy</span>
          </button>
          <button
            @click="clearLogs"
            class="border-border hover:bg-muted flex items-center gap-1 rounded-md border px-3 py-1.5 text-xs font-medium tracking-tight transition active:scale-98 sm:text-sm"
          >
            <Icon name="lucide:x" class="size-3.5 shrink-0" />
            <span>Clear</span>
          </button>
        </div>
      </div>
      <ClientOnly>
        <div
          ref="logPanel"
          class="bg-muted/30 max-h-80 overflow-y-auto rounded-md font-mono text-xs sm:text-sm"
        >
          <div
            v-if="logs.length === 0"
            class="text-muted-foreground p-4 text-center tracking-tight"
          >
            Belum ada aktivitas. Klik Connect untuk mulai.
          </div>
          <div
            v-for="log in logs"
            :key="log.id"
            class="border-border/40 flex flex-col gap-0.5 border-b px-3 py-2 last:border-b-0"
            :class="logColorClass(log.level)"
          >
            <div class="flex items-baseline gap-2">
              <span class="text-muted-foreground shrink-0">{{ log.timestamp }}</span>
              <span class="shrink-0 font-medium uppercase">{{ log.level }}</span>
              <span class="break-all">{{ log.message }}</span>
            </div>
            <pre
              v-if="log.detail"
              class="text-muted-foreground pl-16 break-all whitespace-pre-wrap"
              >{{ log.detail }}</pre
            >
          </div>
        </div>
      </ClientOnly>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useBluetoothPrinter, type LogLevel } from "@/composables/useBluetoothPrinter";
import {
  buildEscPosBitmap,
  buildEscPosNativeQr,
  buildTsplBitmap,
  buildTsplNativeQr,
  renderPrintCanvas,
} from "@/composables/usePrinterCommands";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta(null, {
  title: "Bluetooth Printer Test",
});

type PrintMode = "tspl-native" | "tspl-bitmap" | "escpos-native" | "escpos-bitmap";

const visitorName = ref("Budi Santoso");
const qrData = ref("https://pmone.id/v/test-001");
const previewCanvas = ref<HTMLCanvasElement | null>(null);
const logPanel = ref<HTMLDivElement | null>(null);
const printing = ref(false);

const {
  device,
  status,
  errorMessage,
  savedDeviceName,
  discoveredChars,
  logs,
  isSupported,
  connect,
  disconnect,
  writeChunked,
  selectCharacteristic,
  tryRestoreDevice,
  clearLogs,
  copyLogs,
  addLog,
} = useBluetoothPrinter();

onMounted(() => {
  tryRestoreDevice();
});

const canPrint = computed(() => status.value === "connected");

async function refreshPreview(): Promise<void> {
  if (!import.meta.client || !previewCanvas.value) return;
  try {
    const generated = await renderPrintCanvas({
      name: visitorName.value || " ",
      qrData: qrData.value || " ",
      widthMm: 50,
      heightMm: 50,
      scale: 4,
    });
    const target = previewCanvas.value;
    target.width = generated.width;
    target.height = generated.height;
    const ctx = target.getContext("2d");
    if (ctx) {
      ctx.clearRect(0, 0, target.width, target.height);
      ctx.drawImage(generated, 0, 0);
    }
  } catch (err) {
    console.error("Failed to render preview:", err);
  }
}

watch(
  [previewCanvas, visitorName, qrData],
  () => {
    if (previewCanvas.value) {
      refreshPreview();
    }
  },
  { immediate: true }
);

watch(
  () => logs.value.length,
  () => {
    nextTick(() => {
      if (logPanel.value) {
        logPanel.value.scrollTop = logPanel.value.scrollHeight;
      }
    });
  }
);

async function handleConnect(): Promise<void> {
  try {
    await connect();
    if (status.value === "connected") {
      toast.success("Printer terhubung");
    }
  } catch {
    // sudah ditangani di composable
  }
}

async function handleReconnect(): Promise<void> {
  try {
    await connect(true);
    if (status.value === "connected") {
      toast.success("Printer terhubung");
    }
  } catch {
    // sudah ditangani di composable
  }
}

async function handlePrint(mode: PrintMode): Promise<void> {
  if (!canPrint.value || !visitorName.value || !qrData.value) {
    toast.error("Pastikan printer connected dan form sudah terisi");
    return;
  }

  printing.value = true;
  try {
    addLog("info", `Mulai print mode: ${mode}`);

    let bytes: Uint8Array;

    if (mode === "tspl-native") {
      bytes = buildTsplNativeQr({
        name: visitorName.value,
        qrData: qrData.value,
        widthMm: 50,
        heightMm: 50,
      });
    } else if (mode === "tspl-bitmap") {
      const canvas = await renderPrintCanvas({
        name: visitorName.value,
        qrData: qrData.value,
        widthMm: 50,
        heightMm: 50,
        scale: 1,
      });
      bytes = buildTsplBitmap(canvas, { widthMm: 50, heightMm: 50 });
    } else if (mode === "escpos-native") {
      bytes = buildEscPosNativeQr({
        name: visitorName.value,
        qrData: qrData.value,
        qrSize: 8,
      });
    } else {
      const canvas = await renderPrintCanvas({
        name: visitorName.value,
        qrData: qrData.value,
        widthMm: 50,
        heightMm: 50,
        scale: 1,
      });
      bytes = buildEscPosBitmap(canvas);
    }

    await writeChunked(bytes);
    toast.success("Data terkirim ke printer");
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err);
    toast.error(`Print gagal: ${msg}`);
  } finally {
    printing.value = false;
  }
}

function logColorClass(level: LogLevel): string {
  const map: Record<LogLevel, string> = {
    info: "",
    success: "text-success",
    warn: "text-warning",
    error: "text-destructive",
    data: "text-info",
  };
  return map[level] ?? "";
}
</script>

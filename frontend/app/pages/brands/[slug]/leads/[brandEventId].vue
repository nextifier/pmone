<template>
  <div class="mx-auto max-w-2xl space-y-6 pt-4 pb-16">
    <!-- Back + Title -->
    <div class="flex items-center justify-between gap-x-3">
      <div class="flex min-w-0 items-center gap-x-2.5">
        <NuxtLink
          :to="`/brands/${route.params.slug}`"
          class="text-muted-foreground hover:bg-muted hover:text-foreground flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors"
        >
          <Icon name="hugeicons:arrow-left-01" class="size-5" />
        </NuxtLink>
        <div class="min-w-0 flex-1">
          <h1 class="page-title truncate">Leads</h1>
          <p
            v-if="brandEvent"
            class="page-description truncate"
          >
            {{ brandName }} - {{ eventTitle }}
          </p>
        </div>
      </div>

      <div class="flex shrink-0 items-center gap-x-1.5">
        <Button v-if="canScan" size="sm" @click="openScanner">
          <Icon name="hugeicons:qr-code" class="size-4 shrink-0" />
          Scan badge
        </Button>
        <Button
          variant="outline"
          size="sm"
          :disabled="exporting || !analyticsTotal"
          @click="handleExport"
        >
          <Spinner v-if="exporting" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          Export
        </Button>
      </div>
    </div>

    <!-- Analytics summary -->
    <div class="bg-card rounded-xl border p-4 shadow-xs sm:p-5">
      <div class="flex items-center justify-between gap-x-3">
        <div class="space-y-0.5">
          <p class="text-muted-foreground text-sm tracking-tight">Total leads</p>
          <p class="text-2xl font-semibold tracking-tighter tabular-nums">{{ analyticsTotal }}</p>
        </div>
        <div class="bg-muted text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg">
          <Icon name="hugeicons:agreement-02" class="size-5" />
        </div>
      </div>

      <div v-if="perDay.length" class="mt-4 space-y-2">
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">By day</p>
        <div class="space-y-1.5">
          <div
            v-for="row in perDay"
            :key="row.day"
            class="flex items-center gap-x-3"
          >
            <span class="text-muted-foreground w-24 shrink-0 text-xs tracking-tight tabular-nums sm:text-sm">
              {{ formatDay(row.day) }}
            </span>
            <div class="bg-muted h-2 flex-1 overflow-hidden rounded-full">
              <div
                class="bg-primary h-full rounded-full transition-[width] duration-300 ease-out motion-reduce:transition-none"
                :style="{ width: `${maxPerDay ? (row.total / maxPerDay) * 100 : 0}%` }"
              />
            </div>
            <span class="w-8 shrink-0 text-right text-sm tracking-tight tabular-nums">
              {{ row.total }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Leads table -->
    <div class="space-y-3">
      <!-- Loading Skeleton -->
      <div v-if="loading" class="divide-y overflow-hidden rounded-xl border">
        <div v-for="i in 4" :key="`skeleton-${i}`" class="p-4">
          <div class="flex items-start justify-between gap-x-3">
            <div class="min-w-0 flex-1 space-y-2">
              <Skeleton class="h-4 w-32" />
              <Skeleton class="h-3.5 w-44" />
            </div>
            <Skeleton class="h-3.5 w-20 shrink-0" />
          </div>
        </div>
      </div>

      <!-- Table -->
      <div v-else-if="leads.length" class="overflow-hidden rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead class="hidden sm:table-cell">Email</TableHead>
              <TableHead class="hidden md:table-cell">Phone</TableHead>
              <TableHead class="hidden md:table-cell">Ticket tier</TableHead>
              <TableHead class="text-right">Scanned at</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="lead in leads" :key="lead.id">
              <TableCell>
                <div class="min-w-0">
                  <p class="truncate font-medium tracking-tight">{{ lead.name || "-" }}</p>
                  <p class="text-muted-foreground truncate text-xs tracking-tight sm:hidden">
                    {{ lead.email || "-" }}
                  </p>
                </div>
              </TableCell>
              <TableCell class="hidden text-sm tracking-tight sm:table-cell">
                {{ lead.email || "-" }}
              </TableCell>
              <TableCell class="hidden text-sm tracking-tight tabular-nums md:table-cell">
                {{ lead.phone || "-" }}
              </TableCell>
              <TableCell class="hidden md:table-cell">
                <Badge v-if="lead.ticket_tier" variant="muted" plain>{{ lead.ticket_tier }}</Badge>
                <span v-else class="text-muted-foreground text-sm tracking-tight">-</span>
              </TableCell>
              <TableCell class="text-muted-foreground text-right text-xs tracking-tight whitespace-nowrap sm:text-sm">
                {{ formatDateTime(lead.scanned_at) }}
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <!-- Empty state -->
      <Empty v-else class="border-dashed">
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <Icon name="hugeicons:agreement-02" />
          </EmptyMedia>
          <EmptyTitle>No leads yet</EmptyTitle>
          <EmptyDescription>
            Scan attendee badges at your booth to capture leads.
          </EmptyDescription>
        </EmptyHeader>
        <EmptyContent v-if="canScan">
          <Button size="sm" @click="openScanner">
            <Icon name="hugeicons:qr-code" class="size-4 shrink-0" />
            Scan badge
          </Button>
        </EmptyContent>
      </Empty>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="flex items-center justify-between gap-x-3 pt-1">
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} total
        </p>
        <div class="flex items-center gap-x-1.5">
          <Button
            variant="outline"
            size="sm"
            :disabled="meta.current_page <= 1 || loading"
            @click="goToPage(meta.current_page - 1)"
          >
            Previous
          </Button>
          <Button
            variant="outline"
            size="sm"
            :disabled="meta.current_page >= meta.last_page || loading"
            @click="goToPage(meta.current_page + 1)"
          >
            Next
          </Button>
        </div>
      </div>
    </div>

    <!-- Scanner dialog -->
    <DialogResponsive v-model:open="scannerOpen" dialog-max-width="30rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tighter">Scan badge</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            Point the camera at an attendee badge QR code to capture the lead.
          </p>

          <!-- Camera scanner -->
          <div v-if="scannerSupported" class="mt-4 space-y-3">
            <div class="bg-muted relative aspect-square w-full overflow-hidden rounded-xl border">
              <QrcodeStream
                v-if="scannerOpen"
                :constraints="{ facingMode: 'environment' }"
                :formats="['qr_code']"
                class="size-full object-cover"
                @detect="onCameraDetect"
                @error="onCameraError"
              />
              <div
                class="border-background/70 pointer-events-none absolute inset-6 rounded-lg border-2"
              />
            </div>
            <p v-if="cameraError" class="text-destructive-foreground text-xs tracking-tight sm:text-sm">
              {{ cameraError }}
            </p>
            <p v-else class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Hold steady. The lead is captured automatically once the QR is read.
            </p>
          </div>

          <!-- Unsupported notice -->
          <div
            v-else
            class="bg-muted/50 text-muted-foreground mt-4 flex items-start gap-x-2 rounded-lg border border-dashed p-3 text-xs tracking-tight sm:text-sm"
          >
            <Icon name="hugeicons:information-circle" class="mt-0.5 size-4 shrink-0" />
            <span>
              Live camera scanning is not supported in this browser. Enter the QR token manually
              below instead.
            </span>
          </div>

          <!-- Manual fallback -->
          <div class="mt-4 space-y-2">
            <Label for="manual-qr">QR token</Label>
            <div class="flex items-center gap-x-2">
              <Input
                id="manual-qr"
                v-model="manualToken"
                placeholder="Paste or type the QR token"
                @keyup.enter="captureToken(manualToken)"
              />
              <Button
                type="button"
                :disabled="capturing || !manualToken.trim()"
                @click="captureToken(manualToken)"
              >
                <Spinner v-if="capturing" />
                Capture
              </Button>
            </div>
          </div>

          <div class="mt-4 flex justify-end">
            <Button variant="outline" type="button" @click="scannerOpen = false">Close</Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { computed, defineAsyncComponent, ref } from "vue";
import { toast } from "vue-sonner";

// Client-only async load: vue-qrcode-reader (+ zxing wasm) stays out of the SSR
// module graph; <QrcodeStream> only renders client-side inside the open dialog.
const QrcodeStream = defineAsyncComponent(() =>
  import("vue-qrcode-reader").then((m) => m.QrcodeStream),
);

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const client = useSanctumClient();
const { user } = usePermission();

const brandSlug = computed(() => route.params.slug);
const brandEventId = computed(() => route.params.brandEventId);

// --- Context: resolve brandEventId -> numeric event_id + brand/event titles. ---
const brandEvent = ref(null);
const brandName = computed(() => brandEvent.value?.brand?.name || "Brand");
const eventTitle = computed(() => brandEvent.value?.event?.title || "Event");
const eventId = computed(() => brandEvent.value?.event?.id ?? null);

// Scanning requires either belonging to the brand or being staff and above.
// The backend enforces data isolation; this only hides the UI affordance.
const canScan = computed(() => !!user.value);

usePageMeta(null, {
  title: computed(() => `Leads · ${eventTitle.value}`),
});

const formatDateTime = (value) => {
  if (!value) return "-";
  return new Date(value).toLocaleString("id-ID", {
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const formatDay = (value) => {
  if (!value) return "-";
  return new Date(value).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
  });
};

// --- Leads list (paginated) ---
const leads = ref([]);
const loading = ref(true);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const page = ref(1);
const perPage = 30;

async function fetchLeads() {
  loading.value = true;
  try {
    const res = await client(
      `/api/exhibitor/brands/${brandSlug.value}/leads?per_page=${perPage}&page=${page.value}`
    );
    leads.value = res.data ?? [];
    meta.value = res.meta ?? { current_page: 1, last_page: 1, total: 0 };
  } catch (err) {
    toast.error("Failed to load leads", { description: err?.data?.message || err?.message });
  } finally {
    loading.value = false;
  }
}

function goToPage(next) {
  if (next < 1 || next > meta.value.last_page) return;
  page.value = next;
  fetchLeads();
}

// --- Analytics ---
const analyticsTotal = ref(0);
const perDay = ref([]);
const maxPerDay = computed(() => perDay.value.reduce((m, r) => Math.max(m, r.total), 0));

async function fetchAnalytics() {
  try {
    const res = await client(`/api/exhibitor/brands/${brandSlug.value}/leads/analytics`);
    analyticsTotal.value = res.data?.total ?? 0;
    perDay.value = res.data?.per_day ?? [];
  } catch {
    // Non-fatal: analytics is supplementary.
  }
}

async function fetchContext() {
  try {
    const res = await client(
      `/api/exhibitor/brands/${brandSlug.value}/leads/context?brand_event_id=${brandEventId.value}`
    );
    brandEvent.value = res.data ?? null;
  } catch {
    // Non-fatal: header context falls back to defaults.
  }
}

// --- Scanner ---
// Camera capture is delegated to <QrcodeStream> (vue-qrcode-reader): native
// BarcodeDetector when available, zxing-wasm fallback otherwise - so it works on
// iOS Safari / Firefox. It mounts only while the dialog is open (v-if), so the
// camera is released the moment the dialog closes. captureToken() stays the
// single capture entry point.
const scannerOpen = ref(false);
const manualToken = ref("");
const capturing = ref(false);
const cameraError = ref("");

const scannerSupported = computed(
  () => typeof window !== "undefined" && !!navigator.mediaDevices?.getUserMedia
);

let lastScanToken = "";
let lastScanAt = 0;

function openScanner() {
  manualToken.value = "";
  cameraError.value = "";
  scannerOpen.value = true;
}

function onCameraDetect(codes) {
  const token = codes?.[0]?.rawValue;
  if (!token || capturing.value) return;
  // Debounce repeated detections of the same code within 3s.
  const now = Date.now();
  if (token === lastScanToken && now - lastScanAt < 3000) return;
  lastScanToken = token;
  lastScanAt = now;
  captureToken(token);
}

function onCameraError(err) {
  cameraError.value =
    err?.name === "NotAllowedError"
      ? "Camera access was denied. Enter the QR token manually below."
      : "The camera is unavailable here. Enter the QR token manually below.";
}

const REASON_MESSAGES = {
  ticket_not_found: "This QR code does not match any ticket.",
  order_not_confirmed: "The order for this ticket is not confirmed yet.",
  wrong_event: "This ticket is not valid for this event.",
};

async function captureToken(token) {
  const value = String(token || "").trim();
  if (!value || capturing.value) return;

  if (!eventId.value) {
    toast.error("Cannot capture lead", {
      description: "Event context is still loading. Try again in a moment.",
    });
    return;
  }

  capturing.value = true;
  try {
    const res = await client(`/api/exhibitor/brands/${brandSlug.value}/leads/scan`, {
      method: "POST",
      body: { qr_token: value, event_id: eventId.value },
    });
    const result = res.data ?? {};

    if (result.result === "captured") {
      toast.success(`Lead captured: ${result.lead?.name || "Attendee"}`);
      manualToken.value = "";
      page.value = 1;
      await Promise.all([fetchLeads(), fetchAnalytics()]);
    } else if (result.result === "already_captured") {
      toast.info("Already captured", {
        description: result.lead?.name ? `${result.lead.name} is already in your leads.` : undefined,
      });
      manualToken.value = "";
    } else {
      toast.error("Could not capture lead", {
        description: REASON_MESSAGES[result.reason] || "This badge could not be scanned.",
      });
    }
  } catch (err) {
    toast.error("Scan failed", { description: err?.data?.message || err?.message });
  } finally {
    capturing.value = false;
  }
}

watch(scannerOpen, (open) => {
  // <QrcodeStream> unmounts with the dialog (v-if), releasing the camera; we
  // just clear any stale error message.
  if (!open) cameraError.value = "";
});

// --- Export ---
const exporting = ref(false);

async function handleExport() {
  exporting.value = true;
  try {
    const response = await client(`/api/exhibitor/brands/${brandSlug.value}/leads/export`, {
      responseType: "blob",
    });
    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    const timestamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);
    link.href = downloadUrl;
    link.download = `leads_${brandSlug.value}_${timestamp}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(downloadUrl);
    toast.success("Leads exported");
  } catch (err) {
    toast.error("Export failed", { description: err?.data?.message || err?.message });
  } finally {
    exporting.value = false;
  }
}

onMounted(() => {
  fetchContext();
  fetchLeads();
  fetchAnalytics();
});
</script>

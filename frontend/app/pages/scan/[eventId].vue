<template>
  <div class="mx-auto flex h-full w-full max-w-6xl flex-col space-y-4 pt-4 pb-24 lg:block lg:h-auto lg:pb-6">
    <!-- Permission gate -->
    <div
      v-if="!canScan"
      class="flex min-h-[60vh] flex-col items-center justify-center gap-y-4 p-6 text-center"
    >
      <div class="bg-muted flex size-12 items-center justify-center rounded-full">
        <Icon name="hugeicons:no-access" class="text-muted-foreground size-6" />
      </div>
      <div class="space-y-1.5">
        <h3 class="font-semibold tracking-tighter">No access to the scanner</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          You need the check-in permission to use this scanner. Ask an administrator to grant you
          the scanner role.
        </p>
      </div>
    </div>

    <template v-else>
      <!-- Header + status -->
      <div class="flex flex-col items-start gap-y-4">
        <ButtonBack destination="/scan" force-destination />
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-x-2.5">
            <Icon name="hugeicons:qr-code-01" class="size-5 sm:size-6" />
            <h1 class="page-title">Check-in scanner</h1>
          </div>
          <div class="flex items-center gap-2">
            <Badge :variant="isOnline ? 'success' : 'warning'" v-tippy="onlineTooltip">
              <Transition name="t-tswap" mode="out-in">
                <span :key="isOnline ? 'on' : 'off'" class="inline-block">
                  {{ isOnline ? "Online" : "Offline" }}
                </span>
              </Transition>
            </Badge>
            <Transition name="t-fade-pop">
              <Badge v-if="outbox.length" variant="warning" plain>
                {{ outbox.length }} pending
              </Badge>
            </Transition>
            <ClientOnly>
              <DropdownMenu v-if="printerSupported" :modal="false">
                <DropdownMenuTrigger as-child>
                  <button
                    type="button"
                    class="text-foreground inline-flex w-fit shrink-0 items-center gap-x-1.5 rounded-full border border-foreground/17 px-2 py-1 text-sm font-normal whitespace-nowrap tracking-tight transition hover:bg-muted active:scale-98 data-[state=open]:bg-muted"
                    :aria-label="printerLabel"
                    v-tippy="printerLabel"
                  >
                    <Spinner v-if="printerStatus === 'connecting'" class="size-4 shrink-0" />
                    <span v-else class="size-2 shrink-0 rounded-full" :class="printerDotClass" />
                    <Icon name="hugeicons:printer" class="size-4 shrink-0" />
                    <Transition name="t-tswap" mode="out-in">
                      <span :key="printerBadgeLabel" class="inline-block">{{ printerBadgeLabel }}</span>
                    </Transition>
                  </button>
                </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-60">
                <DropdownMenuLabel class="flex items-center gap-x-2 font-normal">
                  <span
                    class="size-2 shrink-0 rounded-full"
                    :class="printerConnected ? 'bg-success' : 'bg-muted-foreground/40'"
                  />
                  <span class="truncate">{{ printerStatusText }}</span>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  v-if="printerName && !printerConnected"
                  :disabled="printerStatus === 'connecting'"
                  class="gap-x-2"
                  @click="reconnectPrinter"
                >
                  <Icon name="hugeicons:bluetooth" class="size-4 shrink-0" />
                  <span class="truncate">Reconnect to {{ printerName }}</span>
                </DropdownMenuItem>
                <DropdownMenuItem
                  :disabled="printerStatus === 'connecting'"
                  class="gap-x-2"
                  @click="chooseAnotherPrinter"
                >
                  <Icon name="hugeicons:exchange-01" class="size-4 shrink-0" />
                  <span>Choose another device</span>
                </DropdownMenuItem>
                <template v-if="printerName">
                  <DropdownMenuSeparator />
                  <DropdownMenuItem variant="destructive" class="gap-x-2" @click="forgetPrinter">
                    <Icon name="lucide:x" class="size-4 shrink-0" />
                    <span>Forget printer</span>
                  </DropdownMenuItem>
                </template>
                </DropdownMenuContent>
              </DropdownMenu>
            </ClientOnly>
          </div>
        </div>
      </div>

      <!-- Panels: multi-column on lg, single column (BottomNav tabs) on mobile.
           Visibility is pure CSS (hidden lg:block) so it never flashes on lg. -->
      <div class="flex min-h-0 flex-1 flex-col lg:grid lg:grid-cols-[minmax(0,1fr)_clamp(20rem,26vw,24rem)] lg:items-start lg:gap-6">
        <div
          class="t-panel-slide flex min-h-0 flex-1 flex-col lg:block"
          style="--panel-translate-y: 14px"
          :data-open="revealed"
          :class="{ hidden: activeTab !== 'scan' }"
        >
          <ScanPanel :camera-enabled="cameraEnabled" />
        </div>
        <aside class="space-y-6">
          <div
            class="t-panel-slide lg:block"
            style="--panel-translate-y: 14px; transition-delay: 70ms"
            :data-open="revealed"
            :class="{ hidden: activeTab !== 'find' }"
          >
            <FindPanel />
          </div>
          <div
            class="t-panel-slide lg:block"
            style="--panel-translate-y: 14px; transition-delay: 140ms"
            :data-open="revealed"
            :class="{ hidden: activeTab !== 'activity' }"
          >
            <ActivityPanel />
          </div>
        </aside>
      </div>

      <!-- Mobile bottom navigation (hidden on lg via the component) -->
      <BottomNav v-model="activeTab" position="fixed" indicator="pill">
        <BottomNavItem value="find" icon="hugeicons:search-01" label="Find" />
        <BottomNavAction icon="hugeicons:qr-code-01" label="Scan" @select="activeTab = 'scan'" />
        <BottomNavItem value="activity" icon="hugeicons:clock-01" label="Activity" />
      </BottomNav>
    </template>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { BottomNav, BottomNavItem, BottomNavAction } from "@/components/ui/bottom-nav";
import ScanPanel from "@/components/scan/ScanPanel.vue";
import FindPanel from "@/components/scan/FindPanel.vue";
import ActivityPanel from "@/components/scan/ActivityPanel.vue";
import { SCAN_SESSION } from "@/composables/scanSessionKey";
import { useMediaQuery } from "@vueuse/core";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["scan.check_in"],
  layout: "app",
});

const route = useRoute();

const session = useScanSession(String(route.params.eventId));
provide(SCAN_SESSION, session);

const {
  canScan,
  eventTitle,
  isOnline,
  outbox,
  printerSupported,
  printerStatus,
  printerConnected,
  printerError,
  printerName,
  reconnectPrinter,
  chooseAnotherPrinter,
  forgetPrinter,
} = session;

usePageMeta(null, {
  title: computed(() => `Scanner · ${eventTitle.value}`),
});

// lg shows every panel at once; mobile switches via the BottomNav.
const isLg = useMediaQuery("(min-width: 1024px)");
const activeTab = ref("scan");
const cameraEnabled = computed(() => isLg.value || activeTab.value === "scan");

// Panels start hidden and slide in once mounted (transitions-dev panel reveal).
// Flipped after mount so the data-open="false" → "true" transition actually runs.
const revealed = ref(false);
onMounted(() => {
  revealed.value = true;
});

// What the network badge means: when Online, check-ins POST to the server and
// sync instantly; when Offline, scans are queued locally and flushed on
// reconnect.
const onlineTooltip = computed(() =>
  isOnline.value
    ? "Connected - check-ins sync instantly"
    : "No connection - scans are queued and sync when back online",
);

const printerLabel = computed(() => {
  switch (printerStatus.value) {
    case "connected":
      return "Printer connected";
    case "connecting":
      return "Connecting printer…";
    case "error":
      return printerError.value || "Printer error - tap to reconnect";
    default:
      return "Connect printer";
  }
});
// Compact status shown directly in the chip label + its colored dot.
const printerBadgeLabel = computed(() => {
  switch (printerStatus.value) {
    case "connected":
      return "Connected";
    case "connecting":
      return "Connecting…";
    default:
      return "Disconnected";
  }
});
const printerDotClass = computed(() =>
  printerStatus.value === "connected" ? "bg-success" : "bg-destructive",
);
const printerStatusText = computed(() => {
  switch (printerStatus.value) {
    case "connected":
      return printerName.value ? `Connected to ${printerName.value}` : "Printer connected";
    case "connecting":
      return "Connecting…";
    case "error":
      return printerError.value || "Printer error";
    default:
      return printerName.value ? `${printerName.value} (disconnected)` : "No printer connected";
  }
});
</script>

<template>
  <div class="mx-auto w-full max-w-6xl space-y-4 pt-4 pb-24 lg:pb-6">
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
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-x-2.5">
          <Icon name="hugeicons:qr-code" class="size-5 sm:size-6" />
          <h1 class="page-title">Check-in scanner</h1>
        </div>
        <div class="flex items-center gap-2">
          <Badge :variant="isOnline ? 'success' : 'warning'">
            {{ isOnline ? "Online" : "Offline" }}
          </Badge>
          <Badge v-if="outbox.length" variant="warning" plain>
            {{ outbox.length }} pending
          </Badge>
          <ClientOnly>
            <Button
              v-if="printerSupported"
              variant="ghost"
              size="iconSm"
              :class="printerChipClass"
              :aria-label="printerLabel"
              v-tippy="printerLabel"
              :disabled="printerStatus === 'connecting'"
              @click="connectPrinterInteractive"
            >
              <Spinner v-if="printerStatus === 'connecting'" />
              <Icon v-else name="hugeicons:printer" class="size-4" />
            </Button>
          </ClientOnly>
          <Button variant="ghost" size="sm" to="/scan">
            <Icon name="hugeicons:arrow-left-01" class="size-4 shrink-0" />
            <span>Change event</span>
          </Button>
        </div>
      </div>

      <!-- Panels: multi-column on lg, single column (BottomNav tabs) on mobile.
           Visibility is pure CSS (hidden lg:block) so it never flashes on lg. -->
      <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_clamp(20rem,26vw,24rem)] lg:items-start lg:gap-6">
        <div class="lg:block" :class="{ hidden: activeTab !== 'scan' }">
          <ScanPanel :camera-enabled="cameraEnabled" />
        </div>
        <aside class="space-y-6">
          <div class="lg:block" :class="{ hidden: activeTab !== 'find' }">
            <FindPanel />
          </div>
          <div class="lg:block" :class="{ hidden: activeTab !== 'activity' }">
            <ActivityPanel />
          </div>
        </aside>
      </div>

      <!-- Mobile bottom navigation (hidden on lg via the component) -->
      <BottomNav v-model="activeTab" position="fixed" indicator="pill">
        <BottomNavItem value="find" icon="hugeicons:search-01" label="Find" />
        <BottomNavAction icon="hugeicons:qr-code" label="Scan" @select="activeTab = 'scan'" />
        <BottomNavItem value="activity" icon="hugeicons:clock-01" label="Activity" />
      </BottomNav>
    </template>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
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
  printerError,
  connectPrinterInteractive,
} = session;

usePageMeta(null, {
  title: computed(() => `Scanner · ${eventTitle.value}`),
});

// lg shows every panel at once; mobile switches via the BottomNav.
const isLg = useMediaQuery("(min-width: 1024px)");
const activeTab = ref("scan");
const cameraEnabled = computed(() => isLg.value || activeTab.value === "scan");

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
const printerChipClass = computed(() =>
  printerStatus.value === "connected" ? "text-success-foreground" : "text-muted-foreground",
);
</script>

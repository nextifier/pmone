<template>
  <section class="flex flex-col gap-y-4">
    <!-- Event header -->
    <header class="flex items-start gap-x-3">
      <!-- Poster shows uncropped; tap to open the full-size version in a lightbox -->
      <Lightbox
        v-if="posterItems.length"
        :items="posterItems"
        :show-thumbnails="false"
        :show-share="false"
      >
        <template #trigger="{ openAt }">
          <button
            type="button"
            class="group bg-muted relative shrink-0 cursor-zoom-in overflow-hidden rounded-lg border"
            aria-label="View event poster"
            @click="openAt(0)"
          >
            <img :src="posterUrl" :alt="posterAlt" class="h-24 w-auto max-w-28" />
            <span
              class="bg-foreground/0 group-hover:bg-foreground/20 absolute inset-0 flex items-center justify-center transition-colors"
            >
              <Icon
                name="lucide:zoom-in"
                class="text-background size-5 opacity-0 transition-opacity group-hover:opacity-100"
              />
            </span>
          </button>
        </template>
      </Lightbox>
      <div
        v-else
        class="bg-muted text-muted-foreground flex size-16 shrink-0 items-center justify-center rounded-lg border"
      >
        <Icon name="hugeicons:image-01" class="size-6" />
      </div>
      <div class="min-w-0 flex-1">
        <h2 class="truncate text-base font-semibold tracking-tighter">
          {{ eventContext?.title || eventTitle }}
        </h2>
        <p
          v-if="eventContext?.date_label"
          class="text-muted-foreground mt-0.5 flex items-center gap-x-1 truncate text-sm tracking-tight"
        >
          <Icon name="hugeicons:calendar-03" class="size-3.5 shrink-0" />
          {{ eventContext.date_label }}
        </p>
        <p
          v-if="venue"
          class="text-muted-foreground mt-0.5 flex items-center gap-x-1 truncate text-sm tracking-tight"
        >
          <Icon name="hugeicons:location-01" class="size-3.5 shrink-0" />
          {{ venue }}
        </p>
      </div>
    </header>

    <!-- Live camera -->
    <div
      class="bg-foreground relative aspect-[4/3] w-full overflow-hidden rounded-2xl border lg:aspect-auto lg:h-[min(46vh,24rem)]"
    >
      <ClientOnly>
        <QrcodeStream
          v-if="cameraLive"
          :constraints="cameraConstraints"
          :formats="['qr_code']"
          class="size-full object-cover"
          @detect="onCameraDetect"
          @camera-on="handleCameraOn"
          @error="onCameraError"
        />
      </ClientOnly>

      <!-- Switch front/back camera (only when the device has more than one) -->
      <Button
        v-if="cameraLive && hasMultipleCameras"
        variant="outline"
        size="iconSm"
        class="bg-background/10 border-background/30 text-background hover:bg-background/20 hover:text-background absolute top-3 right-3 z-10 backdrop-blur-sm"
        aria-label="Switch camera"
        v-tippy="'Switch camera'"
        @click="switchCamera"
      >
        <Icon name="lucide:switch-camera" class="size-4" />
      </Button>

      <!-- Scan frame overlay (only while live) -->
      <div
        v-if="cameraLive"
        class="pointer-events-none absolute inset-0 flex items-center justify-center"
      >
        <div class="relative size-2/3 max-h-64 max-w-64">
          <span class="border-background absolute top-0 left-0 size-8 rounded-tl-xl border-t-2 border-l-2" />
          <span class="border-background absolute top-0 right-0 size-8 rounded-tr-xl border-t-2 border-r-2" />
          <span class="border-background absolute bottom-0 left-0 size-8 rounded-bl-xl border-b-2 border-l-2" />
          <span class="border-background absolute right-0 bottom-0 size-8 rounded-br-xl border-r-2 border-b-2" />
          <span class="bg-success/80 absolute inset-x-0 top-0 h-0.5 animate-[scanline_2s_ease-in-out_infinite]" />
        </div>
      </div>

      <!-- Non-live states -->
      <div
        v-else
        class="text-background/90 absolute inset-0 flex flex-col items-center justify-center gap-y-3 p-6 text-center"
      >
        <Icon name="hugeicons:camera-off-01" class="size-8" />
        <p class="max-w-xs text-sm tracking-tight">{{ cameraMessage }}</p>
        <Button
          v-if="cameraError"
          variant="outline"
          size="sm"
          class="bg-background/10 border-background/30 text-background hover:bg-background/20 hover:text-background"
          @click="retryCamera"
        >
          <Icon name="hugeicons:refresh" class="size-4 shrink-0" />
          <span>Retry camera</span>
        </Button>
      </div>
    </div>

    <!-- Scanner gun input -->
    <div class="space-y-1.5">
      <div class="relative">
        <Icon
          name="hugeicons:qr-code"
          class="text-muted-foreground absolute top-1/2 left-3 size-5 -translate-y-1/2"
        />
        <Input
          v-model="gunValue"
          data-scanner-gun
          class="h-12 pl-10 text-base tracking-tight"
          placeholder="Scan or paste a ticket code"
          autocomplete="off"
          autocapitalize="off"
          autocorrect="off"
          spellcheck="false"
          enterkeyhint="done"
          @paste="onGunPaste"
        />
      </div>
      <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
        Point a ticket QR at the camera, or fire a scanner gun for instant check-in.
      </p>
    </div>

    <!-- Last scan result -->
    <Transition name="result-pop">
      <div v-if="lastResult" class="rounded-xl border p-4 shadow-xs" :class="resultBannerClass">
        <div class="flex items-start gap-x-3">
          <div
            class="flex size-10 shrink-0 items-center justify-center rounded-full"
            :class="resultIconWrapClass"
          >
            <Icon :name="resultIcon" class="size-6" />
          </div>
          <div class="min-w-0 flex-1">
            <span class="text-base font-semibold tracking-tighter">{{ resultHeadline }}</span>

            <p
              v-if="lastResult.attendee?.name"
              class="mt-0.5 truncate text-lg font-medium tracking-tighter"
            >
              {{ lastResult.attendee.name }}
            </p>

            <p
              v-if="lastResult.attendee?.email"
              class="text-muted-foreground mt-0.5 flex items-center gap-x-1 truncate text-sm tracking-tight"
            >
              <Icon name="hugeicons:mail-01" class="size-3.5 shrink-0" />
              {{ lastResult.attendee.email }}
            </p>

            <p
              v-if="lastResult.result === 'invalid'"
              class="text-muted-foreground mt-1 text-sm tracking-tight"
            >
              {{ resultSubline }}
            </p>

            <div v-else-if="metaBadges.length" class="mt-2 flex flex-wrap gap-1.5">
              <Badge v-for="b in metaBadges" :key="b" variant="muted" plain>{{ b }}</Badge>
            </div>

            <p v-if="checkedInAtLabel" class="text-muted-foreground mt-1 text-sm tracking-tight">
              {{ checkedInAtLabel }}
            </p>

            <p
              v-if="lastResult.warning"
              class="text-warning-foreground mt-1.5 flex items-center gap-x-1 text-sm tracking-tight"
            >
              <Icon name="hugeicons:alert-02" class="size-4 shrink-0" />
              {{ warningText(lastResult.warning) }}
            </p>

            <div
              v-if="
                printerSupported &&
                lastResult.attendee?.qr_token &&
                ['checked_in', 'already_checked_in', 'reprinted'].includes(lastResult.result)
              "
              class="mt-3 flex flex-wrap gap-2"
            >
              <template v-if="lastResult.result === 'already_checked_in'">
                <Button
                  size="sm"
                  variant="outline"
                  :disabled="printing"
                  @click="reprintBadge(lastResult.attendee)"
                >
                  <Spinner v-if="printing" />
                  <Icon v-else name="hugeicons:printer" class="size-4 shrink-0" />
                  <span>{{ printerConnected ? "Reprint badge" : "Connect + reprint" }}</span>
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  :disabled="printing"
                  @click="reissueBadge(lastResult.attendee)"
                >
                  <Icon name="hugeicons:refresh" class="size-4 shrink-0" />
                  <span>Re-issue (lost badge)</span>
                </Button>
              </template>
              <Button
                v-else
                size="sm"
                variant="outline"
                :disabled="printing"
                @click="printBadge(lastResult.attendee, { interactive: true })"
              >
                <Spinner v-if="printing" />
                <Icon v-else name="hugeicons:printer" class="size-4 shrink-0" />
                <span>{{ printerConnected ? "Print badge" : "Connect printer + print" }}</span>
              </Button>
            </div>
          </div>

          <Button
            variant="ghost"
            size="iconSm"
            class="-mt-1 -mr-1 shrink-0"
            aria-label="Dismiss"
            @click="lastResult = null"
          >
            <Icon name="hugeicons:cancel-01" class="size-4" />
          </Button>
        </div>
      </div>
    </Transition>
  </section>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Lightbox } from "@/components/ui/lightbox";
import { Spinner } from "@/components/ui/spinner";
import { SCAN_SESSION } from "@/composables/scanSessionKey";

const props = defineProps({
  cameraEnabled: { type: Boolean, default: false },
});

// Keep vue-qrcode-reader (+ its zxing wasm) out of the SSR module graph.
const QrcodeStream = defineAsyncComponent(() =>
  import("vue-qrcode-reader").then((m) => m.QrcodeStream),
);

const session = inject(SCAN_SESSION);

const {
  eventTitle,
  eventContext,
  dayLabel,
  lastResult,
  resultHeadline,
  resultIcon,
  resultBannerClass,
  resultIconWrapClass,
  resultSubline,
  warningText,
  formatTime,
  cameraSupported,
  cameraError,
  retryCamera,
  onCameraDetect,
  onCameraOn,
  onCameraError,
  printerSupported,
  printerConnected,
  printing,
  printBadge,
  reprintBadge,
  reissueBadge,
} = session;

const gunValue = session.gun.inputValue;
const onGunPaste = session.gun.onPaste;

// Front/back camera toggle. Passing a fresh constraints object re-inits the
// QrcodeStream (stops the old track, requests the new facingMode). The button is
// only shown once we know the device actually has more than one camera.
const facingMode = ref("environment");
const cameraConstraints = computed(() => ({ facingMode: facingMode.value }));
const hasMultipleCameras = ref(false);

const detectCameras = async () => {
  try {
    if (!navigator.mediaDevices?.enumerateDevices) return;
    const devices = await navigator.mediaDevices.enumerateDevices();
    hasMultipleCameras.value = devices.filter((d) => d.kind === "videoinput").length > 1;
  } catch {
    hasMultipleCameras.value = false;
  }
};

// Device labels/count are only reliable once permission is granted (camera on).
const handleCameraOn = (capabilities) => {
  onCameraOn(capabilities);
  detectCameras();
};

const switchCamera = () => {
  facingMode.value = facingMode.value === "environment" ? "user" : "environment";
};

const cameraLive = computed(
  () => props.cameraEnabled && cameraSupported.value && !cameraError.value,
);
const cameraMessage = computed(() => {
  if (!cameraSupported.value) {
    return "Camera scanning isn't available here - use a scanner gun or manual search.";
  }
  if (cameraError.value) return cameraError.value;
  return "Camera paused.";
});

// Spatie media URLs come keyed lqip/sm/md/lg/xl (+ url/alt/width/height).
const poster = computed(() => eventContext.value?.poster_image || null);
const posterUrl = computed(() => poster.value?.sm || poster.value?.md || poster.value?.url || null);
const posterAlt = computed(
  () => poster.value?.alt || eventContext.value?.title || "Event poster",
);
const posterItems = computed(() => {
  const p = poster.value;
  if (!p) return [];
  return [
    {
      sm: p.sm,
      md: p.md,
      lg: p.lg,
      xl: p.xl,
      url: p.url,
      alt: posterAlt.value,
      caption: eventContext.value?.title || undefined,
      downloadUrl: p.original || p.url,
    },
  ];
});

const venue = computed(() => {
  const c = eventContext.value;
  if (!c) return "";
  return [c.location, c.hall].filter(Boolean).join(" · ");
});

const metaBadges = computed(() => {
  const a = lastResult.value?.attendee;
  if (!a || lastResult.value?.result === "invalid") return [];
  const out = [];
  if (a.title) out.push(a.title);
  if (a.tier) out.push(a.tier);
  const d = dayLabel(a);
  if (d) out.push(d);
  return out;
});

const checkedInAtLabel = computed(() => {
  const r = lastResult.value;
  if (r?.result === "already_checked_in" && r.attendee?.checked_in_at) {
    return `Checked in at ${formatTime(r.attendee.checked_in_at)}`;
  }
  return "";
});

const focusGun = () => {
  nextTick(() => {
    const el = document.querySelector("[data-scanner-gun]");
    if (el instanceof HTMLElement) el.focus({ preventScroll: true });
  });
};

onMounted(focusGun);
// Re-focus the gun whenever this panel becomes the active one on mobile.
watch(() => props.cameraEnabled, (on) => on && focusGun());
</script>

<style scoped>
@keyframes scanline {
  0% {
    top: 0;
  }
  50% {
    top: 100%;
  }
  100% {
    top: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  [class*="animate-[scanline"] {
    animation: none;
    top: 50%;
  }
}

.result-pop-enter-active,
.result-pop-leave-active {
  transition:
    opacity 0.18s ease,
    transform 0.18s ease;
}
.result-pop-enter-from,
.result-pop-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media (prefers-reduced-motion: reduce) {
  .result-pop-enter-active,
  .result-pop-leave-active {
    transition: opacity 0.18s ease;
  }
  .result-pop-enter-from,
  .result-pop-leave-to {
    transform: none;
  }
}
</style>

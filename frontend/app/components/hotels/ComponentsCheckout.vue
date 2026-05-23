<!--
  Embedded Xendit Components checkout for hotel reservations.

  Mounts the xendit-components-web SDK against a short-lived `componentsSdkKey`
  persisted on the reservation. The customer never leaves this page: the SDK
  renders the channel picker + action UI inline, and on `session-complete` we
  navigate to /hotels/success. Backend webhook `payment_session.completed` is
  what actually marks the reservation Paid — the SDK event is only the UX
  handoff.

  Built-in picker: mirrors the official xendit/demo-store integration. Calls
  `createChannelPickerComponent()` so Xendit handles channel selection +
  channel-specific UI (QR / VA / card form) end-to-end. `createActionContainerComponent`
  handles 3DS / OTP / redirect actions in an iframe overlay.
-->
<template>
  <div class="space-y-4">
    <div v-if="loading" class="flex items-center justify-center gap-x-2 py-8">
      <Spinner class="size-4 shrink-0" />
      <span class="text-muted-foreground text-sm tracking-tight">
        Memuat opsi pembayaran...
      </span>
    </div>

    <div v-else-if="errorMessage" class="space-y-3">
      <div
        class="border-destructive/40 bg-destructive/10 flex items-start gap-3 rounded-md border p-3"
      >
        <Icon
          name="hugeicons:alert-circle"
          class="text-destructive mt-0.5 size-4 shrink-0"
        />
        <div class="flex-1 text-sm tracking-tight">
          <p class="text-destructive font-medium">{{ errorMessage }}</p>
          <p class="text-muted-foreground mt-1 text-xs tracking-tight sm:text-sm">
            Refresh sesi untuk dapat kunci pembayaran baru.
          </p>
        </div>
      </div>
      <Button class="w-full" :disabled="refreshing" @click="onRefreshSession">
        <Spinner v-if="refreshing" />
        Refresh sesi pembayaran
      </Button>
    </div>

    <div v-else class="space-y-3">
      <p class="text-sm font-medium tracking-tight">Pilih metode pembayaran</p>
      <div ref="pickerContainer" class="min-h-12"></div>
      <div ref="actionContainer" class="min-h-12"></div>
      <Button class="w-full" :disabled="!ready || submitting" @click="onSubmit">
        <Spinner v-if="submitting" />
        {{ submitting ? "Memproses..." : "Bayar sekarang" }}
      </Button>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";

const props = defineProps({
  componentsSdkKey: { type: String, required: true },
  reservationNumber: { type: String, required: true },
  magicLinkToken: { type: String, required: true },
});

const loading = ref(true);
const ready = ref(false);
const submitting = ref(false);
const refreshing = ref(false);
const errorMessage = ref("");
const pickerContainer = ref(null);
const actionContainer = ref(null);

let xenditInstance = null;
const listeners = [];
const mountedElements = [];

function on(eventName, handler) {
  if (!xenditInstance?.addEventListener) return;
  xenditInstance.addEventListener(eventName, handler);
  listeners.push({ eventName, handler });
}

async function init() {
  try {
    const mod = await import("xendit-components-web");
    const XenditComponents = mod.XenditComponents ?? mod.default;
    if (!XenditComponents) {
      throw new Error("XenditComponents constructor not found in module.");
    }
    xenditInstance = new XenditComponents({
      componentsSdkKey: props.componentsSdkKey,
    });

    on("init", () => {
      loading.value = false;
    });
    on("submission-ready", () => {
      ready.value = true;
    });
    on("submission-not-ready", () => {
      ready.value = false;
    });
    on("submission-begin", () => {
      submitting.value = true;
    });
    on("submission-end", () => {
      submitting.value = false;
    });
    on("session-complete", () => {
      submitting.value = false;
      const target = `/hotels/success?ref=${encodeURIComponent(
        props.reservationNumber
      )}&token=${encodeURIComponent(props.magicLinkToken)}`;
      window.location.href = target;
    });
    on("session-expired-or-canceled", () => {
      submitting.value = false;
      errorMessage.value = "Sesi pembayaran sudah berakhir atau dibatalkan.";
    });
    on("fatal-error", (e) => {
      submitting.value = false;
      errorMessage.value =
        e?.message || "Terjadi kesalahan pada komponen pembayaran.";
    });

    // Mount the built-in channel picker (mirrors xendit/demo-store).
    await nextTick();
    const pickerEl = xenditInstance.createChannelPickerComponent?.();
    if (pickerEl && pickerContainer.value) {
      pickerContainer.value.appendChild(pickerEl);
      mountedElements.push(pickerEl);
    }

    const actionEl = xenditInstance.createActionContainerComponent?.({});
    if (actionEl && actionContainer.value) {
      actionContainer.value.appendChild(actionEl);
      mountedElements.push(actionEl);
    }

    // Defensive fallback — if `init` never fires (e.g. SDK API rev change),
    // flip loading off after 2s so the picker is at least visible. The actual
    // payment readiness is still gated on submission-ready.
    setTimeout(() => {
      if (loading.value) loading.value = false;
    }, 2000);
  } catch (err) {
    loading.value = false;
    errorMessage.value = err?.message || "Gagal memuat komponen pembayaran.";
  }
}

function onSubmit() {
  if (!xenditInstance?.submit) return;
  try {
    xenditInstance.submit();
  } catch (err) {
    errorMessage.value = err?.message || "Pembayaran gagal dikirim.";
  }
}

async function onRefreshSession() {
  refreshing.value = true;
  try {
    const res = await $fetch(
      `/api/hotels/reservation/${props.magicLinkToken}/retry-payment`,
      { method: "POST" }
    );
    if (res?.data?.components_sdk_key) {
      // The parent reservation page re-fetches on reload and re-mounts this
      // component with the freshly issued SDK key.
      window.location.reload();
    } else {
      errorMessage.value =
        "Tidak menerima kunci sesi baru. Coba muat ulang halaman.";
    }
  } catch (_err) {
    errorMessage.value =
      "Gagal memperbarui sesi. Coba muat ulang halaman.";
  } finally {
    refreshing.value = false;
  }
}

onMounted(() => {
  init();
});

onBeforeUnmount(() => {
  if (xenditInstance) {
    for (const { eventName, handler } of listeners) {
      try {
        xenditInstance.removeEventListener?.(eventName, handler);
      } catch (_) {
        /* ignore */
      }
    }
    for (const el of mountedElements) {
      try {
        xenditInstance.destroyComponent?.(el);
      } catch (_) {
        /* ignore */
      }
    }
  }
  xenditInstance = null;
  listeners.length = 0;
  mountedElements.length = 0;
});
</script>

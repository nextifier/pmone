<!--
  Embedded Xendit Components checkout for hotel reservations.

  Mounts the xendit-components-web SDK against a short-lived `componentsSdkKey`
  minted by the backend's Sessions API call (COMPONENTS mode). The customer
  never leaves this page: they pick a channel, the SDK renders the channel
  + action UI inline, and on `session-complete` we navigate to /hotels/success.
  Backend webhook `payment_session.completed` is what actually marks the
  reservation Paid — the SDK event is only the UX handoff.

  Custom channel picker: PM One renders its own grid of channel buttons (logos
  from /public/images/payment-methods/) and drives the SDK via setCurrentChannel.
  Falls back to a generic icon for any channel code not in the local map.
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

    <template v-else>
      <div v-if="!currentChannelCode">
        <p class="text-sm font-medium tracking-tight">Pilih metode pembayaran</p>
        <div
          v-if="channels.length"
          class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3"
        >
          <button
            v-for="channel in channels"
            :key="channel.code"
            type="button"
            class="border-border bg-card hover:bg-muted flex items-center gap-2 rounded-lg border p-3 text-left transition"
            @click="pickChannel(channel)"
          >
            <img
              v-if="channel.logo"
              :src="`/images/payment-methods/${channel.logo}`"
              :alt="channel.label"
              class="h-6 w-auto shrink-0"
            />
            <Icon
              v-else
              name="hugeicons:credit-card"
              class="text-muted-foreground size-5 shrink-0"
            />
            <span class="truncate text-sm font-medium tracking-tight">
              {{ channel.label }}
            </span>
          </button>
        </div>
        <p
          v-else
          class="text-muted-foreground mt-3 text-xs tracking-tight sm:text-sm"
        >
          Tidak ada metode pembayaran yang aktif untuk sesi ini.
        </p>
      </div>

      <div v-else class="space-y-3">
        <div class="flex items-center justify-between gap-2">
          <div class="flex min-w-0 items-center gap-2">
            <img
              v-if="currentChannelLogo"
              :src="`/images/payment-methods/${currentChannelLogo}`"
              :alt="currentChannelLabel"
              class="h-6 w-auto shrink-0"
            />
            <Icon
              v-else
              name="hugeicons:credit-card"
              class="text-muted-foreground size-5 shrink-0"
            />
            <span class="truncate text-sm font-medium tracking-tight">
              {{ currentChannelLabel }}
            </span>
          </div>
          <button
            type="button"
            class="text-muted-foreground hover:text-foreground shrink-0 text-xs tracking-tight underline-offset-2 hover:underline"
            @click="resetChannel"
          >
            Ganti metode
          </button>
        </div>

        <div ref="channelContainer" class="min-h-12"></div>
        <div ref="actionContainer" class="min-h-12"></div>

        <Button class="w-full" :disabled="submitting" @click="onSubmit">
          <Spinner v-if="submitting" />
          {{ submitting ? "Memproses..." : "Bayar sekarang" }}
        </Button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";

const props = defineProps({
  componentsSdkKey: { type: String, required: true },
  reservationNumber: { type: String, required: true },
  magicLinkToken: { type: String, required: true },
});

// Mirror of the backend CHANNEL_LOGO_MAP + the v3 normalisation we already do
// in XenditWebhookController::resolveSessionChannel (CARDS -> CREDIT_CARD,
// strip `_VIRTUAL_ACCOUNT`). Unknown channels fall back to a generic icon.
const CHANNEL_LOGO_MAP = {
  BCA: { file: "bca.svg", label: "BCA" },
  BNI: { file: "bni.svg", label: "BNI" },
  BRI: { file: "bri.svg", label: "BRI" },
  MANDIRI: { file: "mandiri.svg", label: "Mandiri" },
  PERMATA: { file: "permata-bank.svg", label: "Permata" },
  BSI: { file: "bsi.svg", label: "BSI" },
  BSS: { file: "bss.svg", label: "Bank Sahabat Sampoerna" },
  CIMB: { file: "cimb-niaga.svg", label: "CIMB Niaga" },
  CIMB_NIAGA: { file: "cimb-niaga.svg", label: "CIMB Niaga" },
  BJB: { file: "bjb.svg", label: "BJB" },
  BNC: { file: "neobank.svg", label: "Neobank" },
  NEOBANK: { file: "neobank.svg", label: "Neobank" },
  MUAMALAT: { file: "bank-muamalat.svg", label: "Muamalat" },
  GOPAY: { file: "gopay.svg", label: "GoPay" },
  OVO: { file: "ovo.svg", label: "OVO" },
  DANA: { file: "dana.svg", label: "DANA" },
  SHOPEEPAY: { file: "shopeepay.svg", label: "ShopeePay" },
  LINKAJA: { file: "link-aja.svg", label: "LinkAja" },
  JENIUSPAY: { file: "jeniuspay.svg", label: "JeniusPay" },
  NEXCASH: { file: "nexcash.svg", label: "NexCash" },
  ASTRAPAY: { file: "astrapay.svg", label: "AstraPay" },
  QRIS: { file: "qris.svg", label: "QRIS" },
  VISA: { file: "visa.svg", label: "Visa" },
  MASTERCARD: { file: "mastercard.svg", label: "Mastercard" },
  AMEX: { file: "amex.svg", label: "AMEX" },
  JCB: { file: "jcb.svg", label: "JCB" },
  CREDIT_CARD: { file: "visa.svg", label: "Kartu Kredit / Debit" },
  DD_BRI: { file: "dd-bri.svg", label: "BRI Direct Debit" },
  BRI_DIRECT_DEBIT: { file: "dd-bri.svg", label: "BRI Direct Debit" },
};

function normaliseChannelCode(code) {
  if (!code || typeof code !== "string") return null;
  let c = code.toUpperCase();
  if (c === "CARDS") return "CREDIT_CARD";
  c = c.replace(/_VIRTUAL_ACCOUNT$/, "");
  return c;
}

const channels = ref([]);
const currentChannelCode = ref(null);
const loading = ref(true);
const submitting = ref(false);
const refreshing = ref(false);
const errorMessage = ref("");
const channelContainer = ref(null);
const actionContainer = ref(null);

let xenditInstance = null;
const listeners = [];
const mountedElements = [];

const currentChannel = computed(() =>
  channels.value.find((c) => c.code === currentChannelCode.value)
);
const currentChannelLabel = computed(
  () => currentChannel.value?.label ?? currentChannelCode.value ?? "Pembayaran"
);
const currentChannelLogo = computed(() => currentChannel.value?.logo ?? null);

function on(eventName, handler) {
  if (!xenditInstance?.addEventListener) return;
  xenditInstance.addEventListener(eventName, handler);
  listeners.push({ eventName, handler });
}

function hydrateChannels() {
  if (!xenditInstance?.getActiveChannels) return;
  try {
    const raw = xenditInstance.getActiveChannels() ?? [];
    const seen = new Set();
    channels.value = raw
      .map((ch) => {
        // The exact channel object shape isn't pinned in the SDK docs — accept
        // any of the common code-bearing fields.
        const rawCode = ch?.code || ch?.channel_code || ch?.id || ch?.name;
        const normalised = normaliseChannelCode(rawCode);
        if (!normalised || seen.has(normalised)) return null;
        seen.add(normalised);
        const mapped = CHANNEL_LOGO_MAP[normalised];
        return {
          code: rawCode,
          normalised,
          label: mapped?.label ?? ch?.name ?? rawCode,
          logo: mapped?.file ?? null,
        };
      })
      .filter(Boolean);
  } catch (err) {
    console.error("xendit components: getActiveChannels failed", err);
  }
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
      hydrateChannels();
      loading.value = false;
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
    on("submission-begin", () => {
      submitting.value = true;
    });
    on("submission-end", () => {
      submitting.value = false;
    });

    // Some SDK builds expose channels before `init` fires; some only after.
    // Try immediately, and if we got nothing, leave the listener to refresh
    // once init lands. A safety timer flips loading off so the empty state
    // surfaces if init never arrives.
    hydrateChannels();
    if (channels.value.length > 0) {
      loading.value = false;
    } else {
      setTimeout(() => {
        if (loading.value) {
          hydrateChannels();
          loading.value = false;
        }
      }, 2000);
    }
  } catch (err) {
    loading.value = false;
    errorMessage.value = err?.message || "Gagal memuat komponen pembayaran.";
  }
}

async function pickChannel(channel) {
  currentChannelCode.value = channel.code;
  await nextTick();
  if (!xenditInstance) return;
  try {
    xenditInstance.setCurrentChannel?.(channel.code);
    const channelEl = xenditInstance.createChannelComponent?.(channel.code);
    if (channelEl && channelContainer.value) {
      channelContainer.value.innerHTML = "";
      channelContainer.value.appendChild(channelEl);
      mountedElements.push(channelEl);
    }
    const actionEl = xenditInstance.createActionContainerComponent?.({});
    if (actionEl && actionContainer.value) {
      actionContainer.value.innerHTML = "";
      actionContainer.value.appendChild(actionEl);
      mountedElements.push(actionEl);
    }
  } catch (err) {
    errorMessage.value =
      err?.message || "Tidak dapat memuat metode pembayaran ini.";
  }
}

function resetChannel() {
  if (xenditInstance) {
    for (const el of mountedElements.splice(0)) {
      try {
        xenditInstance.destroyComponent?.(el);
      } catch (_) {
        /* ignore */
      }
    }
  }
  if (channelContainer.value) channelContainer.value.innerHTML = "";
  if (actionContainer.value) actionContainer.value.innerHTML = "";
  currentChannelCode.value = null;
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
    const res = await $fetch(`/api/hotels/reservation/${props.magicLinkToken}`);
    if (res?.data?.components_sdk_key) {
      // Easiest reliable reset is a hard reload — the parent reservation page
      // re-renders this component with the freshly issued SDK key.
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

<!--
  Embedded Xendit Components checkout for hotel reservations.

  Mounts the xendit-components-web SDK against a short-lived `componentsSdkKey`
  persisted on the reservation, then renders a custom Vue accordion picker over
  `sdk.getActiveChannels()` so EVERY channel - including Virtual Account banks
  (BCA, BNI, MANDIRI, ...) - is selectable. The SDK's built-in
  `createChannelPickerComponent()` would silently omit Virtual Account, which
  is the headline reason for this custom picker.

  For QR / VA / e-wallet action overlays we deliberately do NOT mount an
  inline action container. That makes the SDK render its built-in portal
  modal into `document.body` (with the test-mode "Saya telah melakukan
  pembayaran ini" confirm button) - same behavior as the official
  xendit/demo-store reference integration.

  Backend webhook `payment_session.completed` is what actually marks the
  reservation Paid - the SDK `session-complete` event is only the UX handoff
  that redirects the user to /hotels/success.
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

      <Accordion
        v-if="channelGroups.length > 0"
        type="single"
        collapsible
        v-model="openGroupId"
        class="w-full"
      >
        <AccordionItem
          v-for="group in channelGroups"
          :key="group.id"
          :value="group.id"
          class="bg-background border-border overflow-hidden rounded-xl border"
        >
          <AccordionTrigger class="px-4 py-3 hover:no-underline">
            <span class="text-sm font-semibold tracking-tighter">{{ group.label }}</span>
          </AccordionTrigger>
          <AccordionContent class="px-4 pb-4">
            <!--
              Cards group is special - there's one SDK channel ("CARDS") that
              renders its own multi-brand form (Visa / Mastercard / JCB / Amex
              selector + card number / CVV / expiry iframe-protected fields).
              Mount it directly, no inner row selection needed.
            -->
            <div
              v-if="group.id === 'cards' && group.channels[0]"
              :ref="(el) => bindCardsContainer(el, group.channels[0])"
              class="min-h-12"
            ></div>

            <!--
              All other groups (Bank Transfer / E-Wallet / QR / Direct Debit /
              OTC / Other): list selectable channel rows. Picking one mounts
              that channel's form below the list.
            -->
            <div v-else class="space-y-2">
              <button
                v-for="channel in group.channels"
                :key="getChannelKey(channel)"
                type="button"
                :disabled="submitting"
                :data-selected="isSelected(channel)"
                class="border-border bg-background hover:bg-muted/40 data-[selected=true]:border-foreground data-[selected=true]:bg-muted/30 flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                @click="onPickChannel(channel)"
              >
                <!--
                  Logo wrapper deliberately uses a fixed light background
                  (not bg-card / bg-background) because every channel SVG in
                  our asset set is designed for a white background - several
                  (MANDIRI, AMEX, JCB, CIMB, BCA, PERMATA, ...) use white-on-
                  transparent text fills that disappear in dark mode unless
                  the wrapper forces a light surface.

                  Wider rectangle (w-14 h-9) rather than square because every
                  payment-method SVG in /public/images/payment-methods/ has
                  a 600x400 viewBox (3:2 ratio), and a square wrapper with
                  object-contain shrinks the actual logo content to ~30% of
                  the visible area - looks like an empty box at a glance.
                -->
                <span
                  class="border-border flex h-9 w-14 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-white"
                >
                  <img
                    :src="resolveLogo(channel)"
                    :alt="resolveLabel(channel)"
                    class="max-h-7 max-w-12 object-contain"
                    loading="lazy"
                    @error="(e) => onLogoError(e, channel)"
                  />
                </span>
                <span class="flex-1 text-sm font-medium tracking-tight">
                  {{ resolveLabel(channel) }}
                </span>
                <span
                  v-if="isSelected(channel)"
                  class="bg-foreground size-2.5 shrink-0 rounded-full"
                  aria-hidden="true"
                />
              </button>

              <!--
                Form mount target for the selected channel in this group.
                For VA / QR channels with no inputs, SDK returns an empty or
                instructional element - mounting is still required so submit()
                accepts the channel as current.
              -->
              <div
                v-if="currentChannelInGroup(group)"
                :key="`form-${getChannelKey(currentChannelInGroup(group))}`"
                :ref="(el) => bindFormContainer(el, currentChannelInGroup(group))"
                class="mt-3"
              ></div>
            </div>
          </AccordionContent>
        </AccordionItem>
      </Accordion>

      <div
        v-else
        class="text-muted-foreground rounded-md border border-dashed py-6 text-center text-sm tracking-tight"
      >
        Tidak ada metode pembayaran tersedia untuk sesi ini.
      </div>

      <Button class="w-full" :disabled="!ready || submitting" @click="onSubmit">
        <Spinner v-if="submitting" />
        {{ submitting ? "Memproses..." : "Bayar sekarang" }}
      </Button>

      <!--
        Test-mode shortcut. Visible while the SDK has an action in flight
        (after Bayar sekarang, before action-end / session-complete). Calls
        sdk.simulatePayment which fast-forwards QR / OTC / VA actions to paid
        in sandbox. SDK throws if called outside test mode - we catch + show
        the message inline so the button is safe to render regardless.

        Note: the SDK's own action modal already includes a "Saya telah
        melakukan pembayaran ini" button that serves the same purpose for
        QR / VA flows. This is a backup for cases where the modal is hidden
        (e.g. customer dismissed it accidentally) or for OTC barcode flows
        where there is no in-modal confirm.
      -->
      <div v-if="actionInProgress" class="space-y-2">
        <Button
          variant="outline"
          class="w-full"
          :disabled="simulating"
          @click="onSimulatePayment"
        >
          <Spinner v-if="simulating" />
          <Icon
            v-else
            name="hugeicons:flash"
            class="size-4 shrink-0"
          />
          {{ simulating ? "Mensimulasi..." : "Simulasi pembayaran (test mode)" }}
        </Button>
        <p
          v-if="simulateError"
          class="text-destructive text-xs tracking-tight sm:text-sm"
        >
          {{ simulateError }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import {
  groupChannels,
  getDefaultOpenGroupId,
  getChannelKey,
  normalizeChannelCode,
} from "@/lib/payment-channel-groups";
import { getPaymentLogoUrl, getPaymentChannelLabel } from "@/lib/payment-method-logos";

const props = defineProps({
  componentsSdkKey: { type: String, required: true },
  reservationNumber: { type: String, required: true },
  magicLinkToken: { type: String, required: true },
});

// UI state ---------------------------------------------------------------------
const loading = ref(true);
const ready = ref(false);
const submitting = ref(false);
const refreshing = ref(false);
const errorMessage = ref("");
const actionInProgress = ref(false);
const simulating = ref(false);
const simulateError = ref("");

// Picker state -----------------------------------------------------------------
const channelGroups = ref([]); // [{ id, label, channels[] }]
const openGroupId = ref(""); // Accordion v-model
const selectedChannel = ref(null); // raw SDK channel object

// SDK + bookkeeping ------------------------------------------------------------
let xenditInstance = null;
const listeners = [];
/**
 * Per-channel form element cache. Keys are normalized channel codes; values
 * are HTMLElements returned by sdk.createChannelComponent(). We destroy them
 * all on unmount.
 */
const mountedFormEls = new Map();
/**
 * Track which DOM containers we've already moved each form element into, so
 * Vue re-renders that re-bind a ref don't trigger appendChild thrash.
 */
const mountedContainers = new WeakMap();

function on(eventName, handler) {
  if (!xenditInstance?.addEventListener) return;
  xenditInstance.addEventListener(eventName, handler);
  listeners.push({ eventName, handler });
}

// Logo / label resolution ------------------------------------------------------

const FALLBACK_LOGO = "/images/payment-methods/xendit.svg";

function resolveLogo(channel) {
  const code = normalizeChannelCode(channel.channelCode);
  return getPaymentLogoUrl(code) ?? channel.brandLogoUrl ?? FALLBACK_LOGO;
}

function resolveLabel(channel) {
  const code = normalizeChannelCode(channel.channelCode);
  return getPaymentChannelLabel(code) ?? channel.brandName ?? code ?? "Unknown";
}

function onLogoError(event, channel) {
  // First fallback: brand CDN URL from SDK. Second fallback: generic xendit
  // SVG. Set a data attribute to avoid infinite onerror loops if the brand
  // URL also 404s.
  const img = event.target;
  if (!img) return;
  const stage = img.dataset.fallbackStage || "0";
  if (stage === "0" && channel.brandLogoUrl && img.src !== channel.brandLogoUrl) {
    img.dataset.fallbackStage = "1";
    img.src = channel.brandLogoUrl;
    return;
  }
  if (stage !== "2") {
    img.dataset.fallbackStage = "2";
    img.src = FALLBACK_LOGO;
  }
}

// Selection helpers ------------------------------------------------------------

function isSelected(channel) {
  return selectedChannel.value === channel;
}

function currentChannelInGroup(group) {
  if (!selectedChannel.value) return null;
  return group.channels.includes(selectedChannel.value) ? selectedChannel.value : null;
}

// Form mounting ----------------------------------------------------------------

/**
 * Mount the SDK-generated form HTMLElement for `channel` into `container`.
 * Caches per channel-code so re-selecting the same channel preserves any
 * input values the user typed.
 */
function mountFormFor(container, channel) {
  if (!container || !channel || !xenditInstance) return;
  const code = getChannelKey(channel);

  let formEl = mountedFormEls.get(code);
  if (!formEl) {
    try {
      formEl = xenditInstance.createChannelComponent?.(channel) ?? null;
    } catch (err) {
      // Surface inline error so other channels remain usable.
      container.textContent = "";
      const fail = document.createElement("p");
      fail.className = "text-destructive text-xs tracking-tight sm:text-sm";
      fail.textContent =
        err?.message || "Metode ini tidak dapat dimuat. Pilih yang lain.";
      container.appendChild(fail);
      return;
    }
    if (formEl) {
      mountedFormEls.set(code, formEl);
    }
  }
  if (!formEl) return;

  // Skip re-append if the form is already in this container.
  if (mountedContainers.get(formEl) === container) return;

  container.replaceChildren(formEl);
  mountedContainers.set(formEl, container);
}

function bindFormContainer(el, channel) {
  if (!el || !channel) return;
  mountFormFor(el, channel);
}

function bindCardsContainer(el, channel) {
  if (!el || !channel || !xenditInstance) return;
  // Cards group auto-selects its single channel so submit-ready can fire as
  // soon as the user types a valid card number.
  if (selectedChannel.value !== channel) {
    selectedChannel.value = channel;
    try {
      xenditInstance.setCurrentChannel?.(channel);
    } catch (_) {
      /* ignore - non-fatal */
    }
  }
  mountFormFor(el, channel);
}

// Channel pick handler ---------------------------------------------------------

async function onPickChannel(channel) {
  if (submitting.value) return;
  selectedChannel.value = channel;
  try {
    xenditInstance.setCurrentChannel?.(channel);
  } catch (_) {
    /* ignore - non-fatal */
  }
  await nextTick();
  // The :ref callback `bindFormContainer` will fire automatically once Vue
  // renders the form container div for the newly selected channel.
}

// SDK init ---------------------------------------------------------------------

async function init() {
  try {
    const mod = await import("xendit-components-web");
    const XenditComponents = mod.XenditComponents ?? mod.default;
    if (!XenditComponents) {
      throw new Error("XenditComponents constructor not found in module.");
    }
    // Most SDK theming is handled by the --xendit-color-* CSS variables we
    // bridge to shadcn tokens in main.css. iframeFieldAppearance is for the
    // iframe-protected card fields only - the SDK's own page CSS can't
    // reach inside cross-origin iframes, so we pass colors + font here.
    //
    // Color values resolve at SDK construction time. Since the SDK isn't
    // designed to be re-themed live, we read the current color-mode once.
    // Refreshing the page after switching themes will pick up the new theme.
    const colorMode = useColorMode();
    const isDark = colorMode?.value === "dark";
    const iframeFieldAppearance = {
      inputStyles: {
        color: isDark ? "#f4f4f5" : "#252525",
        backgroundColor: isDark ? "#18181b" : "#ffffff",
      },
      placeholderStyles: {
        color: isDark ? "#71717a" : "#9ca3af",
      },
      // Load the same MinusOne variable font we serve to the rest of the
      // page so iframe-rendered card fields don't look out of place. URL is
      // absolute (origin included) because iframes cross-origin resolve
      // relative URLs against their own document, not the parent's.
      fontFace: {
        source: `url(${
          typeof window !== "undefined" ? window.location.origin : ""
        }/fonts/MinusOne-VF.woff2) format("woff2-variations")`,
        descriptors: {
          fontFamily: "MinusOne",
          fontWeight: "400 1000",
          fontDisplay: "swap",
        },
      },
    };

    xenditInstance = new XenditComponents({
      componentsSdkKey: props.componentsSdkKey,
      iframeFieldAppearance,
    });

    on("init", async () => {
      loading.value = false;
      await nextTick();

      let channels = [];
      try {
        channels = xenditInstance.getActiveChannels?.() ?? [];
      } catch (err) {
        errorMessage.value =
          err?.message || "Gagal memuat daftar metode pembayaran.";
        return;
      }

      if (!channels.length) {
        errorMessage.value = "Tidak ada metode pembayaran tersedia untuk sesi ini.";
        return;
      }

      // SDK channel objects use internal Symbol-keyed proxies that throw when
      // Vue's reactivity wraps them. markRaw skips reactivity for these so the
      // SDK can introspect its own objects without "'get' on proxy" errors.
      const rawGroups = groupChannels(channels).map((group) => ({
        ...group,
        channels: group.channels.map((channel) => markRaw(channel)),
      }));
      channelGroups.value = rawGroups;
      openGroupId.value = getDefaultOpenGroupId(rawGroups) ?? "";
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
    on("submission-end", (event) => {
      submitting.value = false;
      const userMessage = event?.userErrorMessage;
      if (Array.isArray(userMessage) && userMessage.length > 0) {
        // SDK convention is [title, description]. Join with " - " for a
        // single-line surface in our existing errorMessage area; the user
        // can still retry by re-picking the channel.
        errorMessage.value = userMessage.filter(Boolean).join(" - ");
      }
    });
    on("action-begin", () => {
      actionInProgress.value = true;
    });
    on("action-end", () => {
      actionInProgress.value = false;
    });
    on("session-complete", () => {
      actionInProgress.value = false;
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

    // Defensive fallback - if the `init` event never fires (e.g. SDK API rev
    // change or transient network issue), flip loading off so the user sees
    // an error / retry path instead of an indefinite spinner.
    setTimeout(() => {
      if (loading.value) {
        loading.value = false;
        if (channelGroups.value.length === 0 && !errorMessage.value) {
          errorMessage.value = "Komponen pembayaran gagal dimuat.";
        }
      }
    }, 4000);
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

/**
 * Test-mode helper: calls the SDK's `simulatePayment()` endpoint to fast-
 * forward a QR / OTC / VA action to "paid" without actually scanning a QR or
 * transferring real money. Only meaningful between `action-begin` and
 * `action-end`. SDK throws if not in test mode - caught + surfaced inline.
 */
function onSimulatePayment() {
  if (!xenditInstance?.simulatePayment) return;
  simulating.value = true;
  simulateError.value = "";
  try {
    xenditInstance.simulatePayment();
  } catch (err) {
    simulateError.value =
      err?.message || "Simulasi gagal. Fitur ini hanya tersedia di mode test.";
  } finally {
    // action-end / session-complete listeners flip actionInProgress back.
    setTimeout(() => {
      simulating.value = false;
    }, 1500);
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
      window.location.reload();
    } else {
      errorMessage.value =
        "Tidak menerima kunci sesi baru. Coba muat ulang halaman.";
    }
  } catch (_err) {
    errorMessage.value = "Gagal memperbarui sesi. Coba muat ulang halaman.";
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
    for (const formEl of mountedFormEls.values()) {
      try {
        xenditInstance.destroyComponent?.(formEl);
      } catch (_) {
        /* ignore */
      }
    }
  }
  xenditInstance = null;
  listeners.length = 0;
  mountedFormEls.clear();
});
</script>

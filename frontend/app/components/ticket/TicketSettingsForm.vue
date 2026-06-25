<template>
  <div class="flex flex-col gap-y-4">
    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner class="size-5" />
    </div>

    <template v-else>
      <!-- Enable toggle -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:ticket-01" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 flex-1 space-y-1">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h3 class="text-base font-semibold tracking-tighter">Ticketing</h3>
              <Badge v-if="form.tickets_enabled" variant="success" icon="hugeicons:checkmark-circle-02">
                Active
              </Badge>
              <Badge v-else variant="muted">Disabled</Badge>
            </div>
            <p class="text-muted-foreground text-sm tracking-tight">
              Control whether tickets are sold for this event.
            </p>
          </div>
        </div>

        <div class="frame-panel space-y-6">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex-1 space-y-1 text-sm tracking-tight">
              <p class="font-medium">Enable ticketing for this event</p>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                {{
                  form.tickets_enabled
                    ? "Tickets, event days, price phases, and sessions are live. Tickets you mark active show on the public event website."
                    : "Turn on to create tickets, event days, price phases, and sessions. Tickets only appear on the public website once this is enabled."
                }}
              </p>
            </div>
            <Switch :model-value="form.tickets_enabled" :disabled="toggling" @update:model-value="onToggleEnabled" />
          </div>
        </div>
      </div>

      <!-- General -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:settings-02" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tighter">General</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Time zone and cross-day rules for ticket validity and scanning.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y !px-0 !py-0">
          <div class="px-4 py-5 lg:px-6">
            <div class="max-w-sm space-y-2">
              <div class="space-y-1">
                <Label for="ticket-timezone">Time zone</Label>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  IANA time zone used to evaluate ticket validity and price phase windows.
                </p>
              </div>
              <Select
                :model-value="form.timezone"
                @update:model-value="
                  (v) => {
                    if (v) {
                      form.timezone = v;
                      save();
                    }
                  }
                "
              >
                <SelectTrigger id="ticket-timezone" class="w-full">
                  <SelectValue placeholder="Select time zone" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="tz in timezoneOptions" :key="tz.value" :value="tz.value">
                    {{ tz.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6">
            <div class="space-y-1">
              <Label for="ticket-allow-cross-day" class="cursor-pointer">
                Allow cross-day validity
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                When on, entry tickets valid for multiple days can be scanned on any of their valid
                days. When off, each day is scanned independently.
              </p>
            </div>
            <Switch id="ticket-allow-cross-day" v-model="form.allow_cross_day" />
          </div>
        </div>
      </div>

      <!-- Defaults -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:layers-01" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tighter">New Ticket Defaults</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Pre-filled values when creating a ticket. Leave a number blank for no default.
            </p>
          </div>
        </div>

        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-2">
            <div class="space-y-2">
              <Label for="ticket-default-min-quantity">Default min quantity</Label>
              <InputNumber
                id="ticket-default-min-quantity"
                v-model="form.default_min_quantity"
                :min="1"
                placeholder="1"
                @blur="save"
              />
            </div>
            <div class="space-y-2">
              <Label for="ticket-default-max-quantity">Default max quantity</Label>
              <InputNumber
                id="ticket-default-max-quantity"
                v-model="form.default_max_quantity"
                :min="1"
                placeholder="No limit"
                @blur="save"
              />
            </div>
            <div class="space-y-2">
              <Label for="ticket-default-stock">Default stock</Label>
              <InputNumber
                id="ticket-default-stock"
                v-model="form.default_stock"
                :min="0"
                placeholder="Unlimited"
                @blur="save"
              />
            </div>
            <div class="flex items-center justify-between gap-3 sm:col-span-2">
              <div class="space-y-1">
                <Label for="ticket-default-print" class="cursor-pointer">Print on redeem (default)</Label>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  When on, new add-on tickets default to printing a physical pass on redemption.
                </p>
              </div>
              <Switch id="ticket-default-print" v-model="form.default_print_on_redeem" />
            </div>
          </div>
        </div>
      </div>

      <!-- E-ticket sign-in -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:dashboard-square-01" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tighter">Visitor Sign-in</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              The one-tap dashboard sign-in shown on each e-ticket and ticket email.
            </p>
          </div>
        </div>

        <div class="frame-panel">
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
              <Label for="ticket-login-button" class="cursor-pointer">
                Show "Go to dashboard" button
              </Label>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Ticket holders can open their account in one tap, no password needed, to view and
                manage their tickets, orders, and profile. The button appears on the e-ticket page
                and in the ticket email (only when a ticket is linked to an account). Note: anyone the
                e-ticket link is shared with can use it, so turn this off if you prefer holders sign
                in manually.
              </p>
            </div>
            <Switch id="ticket-login-button" v-model="form.login_button_enabled" />
          </div>
        </div>
      </div>

      <!-- Purchase Terms -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:agreement-02" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tighter">Purchase Terms</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              These terms appear at checkout on the event website. Buyers must agree to them before
              completing a purchase. Add each language you need.
            </p>
          </div>
        </div>

        <div class="frame-panel space-y-4">
          <Tabs v-model="activeTermsLocale" variant="segmented">
            <TabsList>
              <TabsIndicator />
              <TabsTrigger v-for="locale in LOCALES" :key="locale.value" :value="locale.value">
                {{ locale.label }}
              </TabsTrigger>
            </TabsList>
          </Tabs>

          <div class="space-y-2">
            <TipTapEditor
              v-model="termsField"
              placeholder="Purchase terms (rich text)"
              :sticky="false"
              min-height="180px"
            />
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              English is recommended as the default. Other languages are optional.
            </p>
          </div>

          <div class="flex justify-end">
            <Button :disabled="savingTerms" @click="saveTerms">
              <Spinner v-if="savingTerms" class="size-4" />
              Save terms
            </Button>
          </div>
        </div>
      </div>

      <!-- Payment Channels -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:credit-card" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 flex-1 space-y-1">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h3 class="text-base font-semibold tracking-tighter">Payment Channels</h3>
              <Badge v-if="selectedChannelCount > 0" variant="default">
                {{ selectedChannelCount }} selected
              </Badge>
              <Badge v-else variant="muted">All accepted</Badge>
            </div>
            <p class="text-muted-foreground text-sm tracking-tight">
              Restrict ticket checkout to specific payment channels, e.g. for a bank-sponsored
              event. Leave everything unchecked to accept every channel enabled on your gateway.
            </p>
          </div>
        </div>

        <div class="frame-panel space-y-4">
          <div v-if="channelsLoading" class="flex items-center justify-center py-6">
            <Spinner class="size-5" />
          </div>

          <template v-else>
            <p v-if="!gatewayConfigured" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              No active payment gateway yet, so the full list of supported channels is shown. Your
              selection is saved and applied once a gateway is connected.
            </p>

            <div v-if="channelOptions.length" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
              <label
                v-for="channel in channelOptions"
                :key="channel.code"
                :class="[
                  'flex cursor-pointer items-center gap-x-3 rounded-lg border px-3 py-2.5 transition-colors',
                  isChannelSelected(channel.code)
                    ? 'border-primary bg-primary/5'
                    : 'border-border hover:bg-muted/40',
                ]"
              >
                <Checkbox
                  :model-value="isChannelSelected(channel.code)"
                  @update:model-value="(v) => toggleChannel(channel.code, v)"
                />
                <img
                  v-if="channel.logo_url"
                  :src="channel.logo_url"
                  :alt="channel.label"
                  class="h-5 w-9 shrink-0 object-contain"
                />
                <span class="min-w-0 flex-1 truncate text-sm font-medium tracking-tight">
                  {{ channel.label }}
                </span>
              </label>
            </div>

            <p v-else class="text-muted-foreground text-sm tracking-tight">
              No payment channels are available to select.
            </p>

            <div v-if="selectedChannelCount > 0" class="flex items-center justify-between gap-3">
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Only the selected channels will appear at checkout.
              </p>
              <Button variant="ghost" size="sm" @click="clearChannels">Clear</Button>
            </div>
          </template>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { InputNumber } from "@/components/ui/input-number";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import { computed, onMounted, ref, watch } from "vue";
import { toast } from "vue-sonner";

const LOCALES = [
  { value: "en", label: "English" },
  { value: "id", label: "Indonesian" },
  { value: "ja", label: "日本語" },
  { value: "ko", label: "한국어" },
  { value: "zh", label: "中文" },
];

const EMPTY_TRANSLATABLE = () => ({ en: "", id: "", ja: "", ko: "", zh: "" });

const activeTermsLocale = ref("en");

const props = defineProps({
  event: { type: Object, required: true },
});

const route = useRoute();
const client = useSanctumClient();
const baseUrl = computed(() => `/api/events/${props.event.id}/ticket-settings`);

const loading = ref(true);
const toggling = ref(false);
const savingTerms = ref(false);

const channelOptions = ref([]);
const channelsLoading = ref(true);
const gatewayConfigured = ref(true);

const form = ref({
  tickets_enabled: false,
  allow_cross_day: false,
  timezone: "Asia/Jakarta",
  default_min_quantity: 1,
  default_max_quantity: null,
  default_stock: null,
  default_print_on_redeem: false,
  login_button_enabled: true,
  terms: EMPTY_TRANSLATABLE(),
  allowed_payment_channels: [],
});

const selectedChannelCount = computed(() => form.value.allowed_payment_channels?.length ?? 0);

function isChannelSelected(code) {
  return (form.value.allowed_payment_channels ?? []).includes(code);
}

function toggleChannel(code, checked) {
  const set = new Set(form.value.allowed_payment_channels ?? []);
  if (checked) {
    set.add(code);
  } else {
    set.delete(code);
  }
  form.value.allowed_payment_channels = [...set];
  save();
}

function clearChannels() {
  if (selectedChannelCount.value === 0) return;
  form.value.allowed_payment_channels = [];
  save();
}

const termsField = computed({
  get: () => form.value.terms?.[activeTermsLocale.value] ?? "",
  set: (value) => {
    form.value.terms = { ...form.value.terms, [activeTermsLocale.value]: value };
  },
});

// Common IANA zones. The field still accepts any value the API stored even if
// it is not listed - the Select keeps the bound value.
const timezoneOptions = [
  { value: "Asia/Jakarta", label: "Asia/Jakarta (WIB)" },
  { value: "Asia/Makassar", label: "Asia/Makassar (WITA)" },
  { value: "Asia/Jayapura", label: "Asia/Jayapura (WIT)" },
  { value: "Asia/Singapore", label: "Asia/Singapore" },
  { value: "Asia/Kuala_Lumpur", label: "Asia/Kuala_Lumpur" },
  { value: "Asia/Bangkok", label: "Asia/Bangkok" },
  { value: "Asia/Manila", label: "Asia/Manila" },
  { value: "Asia/Tokyo", label: "Asia/Tokyo" },
  { value: "Asia/Seoul", label: "Asia/Seoul" },
  { value: "Asia/Shanghai", label: "Asia/Shanghai" },
  { value: "Asia/Hong_Kong", label: "Asia/Hong_Kong" },
  { value: "Asia/Dubai", label: "Asia/Dubai" },
  { value: "Australia/Sydney", label: "Australia/Sydney" },
  { value: "Europe/London", label: "Europe/London" },
  { value: "Europe/Paris", label: "Europe/Paris" },
  { value: "America/New_York", label: "America/New_York" },
  { value: "America/Los_Angeles", label: "America/Los_Angeles" },
  { value: "UTC", label: "UTC" },
];

let lastSavedSnapshot = null;
let saving = false;
let savePending = false;

function buildPayload() {
  return {
    allow_cross_day: form.value.allow_cross_day,
    timezone: form.value.timezone,
    default_min_quantity: normalizeInt(form.value.default_min_quantity),
    default_max_quantity: normalizeInt(form.value.default_max_quantity),
    default_stock: normalizeInt(form.value.default_stock),
    default_print_on_redeem: form.value.default_print_on_redeem,
    login_button_enabled: form.value.login_button_enabled,
    terms: cleanTranslatable(form.value.terms),
    allowed_payment_channels: [...(form.value.allowed_payment_channels ?? [])],
  };
}

function cleanTranslatable(t) {
  const out = {};
  for (const [k, v] of Object.entries(t ?? {})) {
    const trimmed = v == null ? "" : String(v).trim();
    if (trimmed.length > 0) out[k] = trimmed;
  }
  return out;
}

function normalizeInt(v) {
  if (v === "" || v === null || v === undefined) return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

async function load() {
  loading.value = true;
  try {
    const res = await client(baseUrl.value);
    const d = res?.data ?? {};
    form.value = {
      tickets_enabled: !!d.tickets_enabled,
      allow_cross_day: !!d.allow_cross_day,
      timezone: d.timezone ?? "Asia/Jakarta",
      default_min_quantity: d.default_min_quantity ?? 1,
      default_max_quantity: d.default_max_quantity ?? null,
      default_stock: d.default_stock ?? null,
      default_print_on_redeem: !!d.default_print_on_redeem,
      login_button_enabled: d.login_button_enabled !== false,
      terms: { ...EMPTY_TRANSLATABLE(), ...(d.terms && typeof d.terms === "object" ? d.terms : {}) },
      allowed_payment_channels: Array.isArray(d.allowed_payment_channels)
        ? [...d.allowed_payment_channels]
        : [],
    };
    lastSavedSnapshot = JSON.stringify(buildPayload());
  } catch (err) {
    toast.error("Failed to load ticket settings");
  } finally {
    loading.value = false;
  }
}

// Channels the admin can pick from: the canonical catalog intersected with the
// channels actually enabled on the project's gateway (falls back to the full
// catalog when no gateway is connected yet).
async function loadChannels() {
  channelsLoading.value = true;
  try {
    const res = await client(`${baseUrl.value}/payment-channels`);
    channelOptions.value = Array.isArray(res?.data) ? res.data : [];
    gatewayConfigured.value = res?.meta?.gateway_configured !== false;
  } catch (err) {
    channelOptions.value = [];
  } finally {
    channelsLoading.value = false;
  }
}

async function save() {
  const payload = buildPayload();
  const snapshot = JSON.stringify(payload);
  if (snapshot === lastSavedSnapshot) return;

  if (saving) {
    savePending = true;
    return;
  }

  saving = true;
  try {
    await client(baseUrl.value, { method: "PUT", body: payload });
    lastSavedSnapshot = snapshot;
    toast.success("Ticket settings updated");
  } catch (err) {
    toast.error("Failed to save", { description: err?.data?.message || err?.message });
  } finally {
    saving = false;
    if (savePending) {
      savePending = false;
      save();
    }
  }
}

// Purchase terms persist on an explicit button press (rich-text editor has no
// reliable blur), surfacing a dedicated spinner while the PUT is in flight.
async function saveTerms() {
  if (savingTerms.value) return;
  savingTerms.value = true;
  try {
    await save();
  } finally {
    savingTerms.value = false;
  }
}

// The enable toggle is the one field that should persist immediately and
// independently - it gates the whole feature, so we send only that flag.
async function onToggleEnabled(next) {
  if (toggling.value) return;
  const previous = form.value.tickets_enabled;
  form.value.tickets_enabled = next;
  toggling.value = true;
  try {
    await client(baseUrl.value, { method: "PUT", body: { tickets_enabled: next } });
    toast.success(next ? "Ticketing enabled" : "Ticketing disabled");
    // Refresh the parent event so the Tickets section reflects the new state.
    await refreshNuxtData(`event-${route.params.username}-${route.params.eventSlug}`);
  } catch (err) {
    form.value.tickets_enabled = previous;
    toast.error("Failed to toggle ticketing", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    toggling.value = false;
  }
}

// Toggles persist on change; numeric/text fields persist on blur (above) to
// avoid a request per keystroke.
watch(
  () => [form.value.allow_cross_day, form.value.default_print_on_redeem, form.value.login_button_enabled],
  () => save()
);

onMounted(() => {
  load();
  loadChannels();
});
</script>

<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h2 class="page-title">Hotel Reservations</h2>
      <p class="page-description">
        Enable hotel booking for this project and configure how the Hotels section, notification
        emails, and PDFs behave. Changes are saved automatically.
      </p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner class="size-5" />
    </div>

    <div v-else class="flex flex-col gap-y-4">
      <!-- Booking toggle -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:hotel-01" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 flex-1 space-y-1">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h3 class="text-base font-semibold tracking-tight">Booking</h3>
              <Badge v-if="hotelEnabled" variant="success" icon="hugeicons:checkmark-circle-02">
                Active
              </Badge>
              <Badge v-else variant="muted">Disabled</Badge>
            </div>
            <p class="text-muted-foreground text-sm tracking-tight">
              Control the hotel booking feature for every event in this project.
            </p>
          </div>
        </div>

        <div class="frame-panel space-y-6">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex-1 space-y-1 text-sm tracking-tight">
              <p class="font-medium">Enable booking for this project</p>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                {{
                  hotelEnabled
                    ? "The Hotels and Reservations tabs are visible on every event. Public visitors can book accommodation and admins can record manual reservations."
                    : "Turn on to expose Hotels and Reservations tabs, allotment management, and the public booking flow for all events in this project."
                }}
              </p>
            </div>
            <Switch
              :model-value="hotelEnabled"
              :disabled="hotelToggling || (!canEnableHotel && !hotelEnabled)"
              @update:model-value="onToggleHotel"
            />
          </div>

          <!-- Payment gateway guard -->
          <div
            v-if="!canEnableHotel"
            class="border-warning/40 bg-warning/10 flex items-start gap-3 rounded-md border p-3"
          >
            <Icon
              name="hugeicons:alert-circle"
              class="text-warning-foreground mt-0.5 size-4 shrink-0"
            />
            <div class="flex-1 text-sm tracking-tight">
              <p class="text-warning-foreground font-medium">Payment gateway required</p>
              <p class="text-muted-foreground mt-1 text-xs tracking-tight sm:text-sm">
                {{
                  hotelEnabled
                    ? "This project has no active payment gateway. Public booking is currently blocked. Set up a gateway to restore reservations."
                    : "Hotel Reservation needs an active payment gateway on this project before it can be enabled. Without it, guests cannot complete bookings."
                }}
              </p>
              <NuxtLink
                :to="paymentGatewaysUrl"
                class="text-primary mt-2 inline-flex items-center gap-x-1 text-sm font-medium tracking-tight hover:underline"
              >
                <Icon name="hugeicons:credit-card" class="size-4 shrink-0" />
                Set up payment gateway
                <Icon name="hugeicons:arrow-right-01" class="size-3.5 shrink-0" />
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>

      <!-- Website display -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:globe-02" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Website Display</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Configure how the Hotels section renders on the public event website.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y !px-0 !py-0">
          <div class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6">
            <div class="space-y-1">
              <Label
                for="hotels-show-estimated-price"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show estimated price in foreign currency
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                When on, hotel cards show an approximate price in the selected currency next to the
                Rupiah price. The estimate is informational; bookings are always charged in IDR.
              </p>
            </div>
            <Switch
              id="hotels-show-estimated-price"
              v-model="form.show_estimated_price_in_foreign_currency"
            />
          </div>

          <div v-if="form.show_estimated_price_in_foreign_currency" class="px-4 py-5 lg:px-6">
            <div class="max-w-xs space-y-2">
              <div class="space-y-1">
                <Label>Foreign currency</Label>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  Used to estimate hotel prices for international visitors.
                </p>
              </div>
              <Popover v-model:open="currencyOpen">
                <PopoverTrigger as-child>
                  <button
                    class="border-border data-[placeholder]:text-muted-foreground flex h-9 w-full items-center gap-1.5 rounded-md border bg-transparent px-3 text-sm tracking-tight shadow-xs"
                  >
                    <template v-if="form.estimated_price_currency">
                      <Flag :country="currencyCountry(form.estimated_price_currency)" />
                      <span class="font-medium">{{ form.estimated_price_currency }}</span>
                      <span class="text-muted-foreground truncate text-sm">{{
                        currencyName(form.estimated_price_currency)
                      }}</span>
                    </template>
                    <span v-else class="text-muted-foreground">Select currency</span>
                    <Icon
                      name="hugeicons:unfold-more"
                      class="text-muted-foreground ml-auto size-3.5 shrink-0"
                    />
                  </button>
                </PopoverTrigger>
                <PopoverContent class="w-[280px] p-0">
                  <Command>
                    <CommandInput placeholder="Search currency..." />
                    <CommandEmpty>No currency found.</CommandEmpty>
                    <CommandList>
                      <CommandGroup>
                        <CommandItem
                          v-for="currency in currencyOptions"
                          :key="currency.code"
                          :value="`${currency.code} ${currency.name}`"
                          class="gap-2"
                          @select="
                            () => {
                              form.estimated_price_currency = currency.code;
                              currencyOpen = false;
                              save();
                            }
                          "
                        >
                          <Flag :country="currency.country" :country-name="currency.name" />
                          <span class="font-medium">{{ currency.code }}</span>
                          <span class="text-muted-foreground flex-1 truncate text-sm">{{
                            currency.name
                          }}</span>
                          <Icon
                            v-if="form.estimated_price_currency === currency.code"
                            name="hugeicons:tick-02"
                            class="text-foreground size-4 shrink-0"
                          />
                        </CommandItem>
                      </CommandGroup>
                    </CommandList>
                  </Command>
                </PopoverContent>
              </Popover>
            </div>
          </div>
        </div>
      </div>

      <!-- Notification emails -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:mail-01" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Booking Notification Email</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Staff recipients emailed when a hotel booking is confirmed or cancelled.
            </p>
          </div>
        </div>

        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-y-6">
            <div class="space-y-3">
              <div class="space-y-1">
                <Label>To (Recipients)</Label>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  Primary staff recipients for booking notifications
                </p>
              </div>
              <div class="space-y-2">
                <div
                  v-for="(email, index) in form.hotel_notification.to"
                  :key="`hotel-to-${index}`"
                  class="flex items-center gap-1.5"
                >
                  <Input
                    v-model="form.hotel_notification.to[index]"
                    type="email"
                    placeholder="email@example.com"
                    @blur="save"
                  />
                  <button
                    type="button"
                    @click="removeRecipient(form.hotel_notification.to, index)"
                    class="text-destructive hover:text-destructive/80 flex size-9 items-center justify-center rounded-lg transition"
                  >
                    <Icon name="hugeicons:delete-01" class="size-4" />
                  </button>
                </div>
              </div>
              <button
                type="button"
                @click="form.hotel_notification.to.push('')"
                class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition"
              >
                <Icon name="hugeicons:add-01" class="size-4" />
                Add To Email
              </button>
            </div>

            <div class="space-y-3">
              <div class="space-y-1">
                <Label>CC (Carbon Copy)</Label>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  Optional CC recipients
                </p>
              </div>
              <div v-if="form.hotel_notification.cc.length > 0" class="space-y-2">
                <div
                  v-for="(email, index) in form.hotel_notification.cc"
                  :key="`hotel-cc-${index}`"
                  class="flex items-center gap-1.5"
                >
                  <Input
                    v-model="form.hotel_notification.cc[index]"
                    type="email"
                    placeholder="email@example.com"
                    @blur="save"
                  />
                  <button
                    type="button"
                    @click="removeRecipient(form.hotel_notification.cc, index)"
                    class="text-destructive hover:text-destructive/80 flex size-9 items-center justify-center rounded-lg transition"
                  >
                    <Icon name="hugeicons:delete-01" class="size-4" />
                  </button>
                </div>
              </div>
              <button
                type="button"
                @click="form.hotel_notification.cc.push('')"
                class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition"
              >
                <Icon name="hugeicons:add-01" class="size-4" />
                Add CC Email
              </button>
            </div>

            <div class="space-y-3">
              <div class="space-y-1">
                <Label>BCC (Blind Carbon Copy)</Label>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  Optional BCC recipients
                </p>
              </div>
              <div v-if="form.hotel_notification.bcc.length > 0" class="space-y-2">
                <div
                  v-for="(email, index) in form.hotel_notification.bcc"
                  :key="`hotel-bcc-${index}`"
                  class="flex items-center gap-1.5"
                >
                  <Input
                    v-model="form.hotel_notification.bcc[index]"
                    type="email"
                    placeholder="email@example.com"
                    @blur="save"
                  />
                  <button
                    type="button"
                    @click="removeRecipient(form.hotel_notification.bcc, index)"
                    class="text-destructive hover:text-destructive/80 flex size-9 items-center justify-center rounded-lg transition"
                  >
                    <Icon name="hugeicons:delete-01" class="size-4" />
                  </button>
                </div>
              </div>
              <button
                type="button"
                @click="form.hotel_notification.bcc.push('')"
                class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition"
              >
                <Icon name="hugeicons:add-01" class="size-4" />
                Add BCC Email
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Email subject templates -->
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:mail-edit-02" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Email Subject Templates</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Customize the subject for each hotel reservation email. Leave a field blank to use
              the default. Placeholders:
              <code class="bg-muted text-foreground rounded px-1 py-0.5 font-mono"
                >{reservation_number}</code
              >,
              <code class="bg-muted text-foreground rounded px-1 py-0.5 font-mono">{hotel}</code>,
              <code class="bg-muted text-foreground rounded px-1 py-0.5 font-mono">{event}</code>,
              <code class="bg-muted text-foreground rounded px-1 py-0.5 font-mono">{guest}</code>,
              <code class="bg-muted text-foreground rounded px-1 py-0.5 font-mono">{project}</code>,
              <code class="bg-muted text-foreground rounded px-1 py-0.5 font-mono">{status}</code>
              (staff only).
            </p>
          </div>
        </div>

        <div class="frame-panel">
          <div class="space-y-4">
            <div class="space-y-2">
              <Label for="email-subject-guest-paid">Guest - Booking Confirmed</Label>
              <Input
                id="email-subject-guest-paid"
                v-model="form.email_subjects.guest_paid"
                type="text"
                placeholder="Hotel Booking Confirmed: {reservation_number} - {project}"
                maxlength="120"
                @blur="save"
              />
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Sent to the guest after their payment is received.
              </p>
            </div>

            <div class="space-y-2">
              <Label for="email-subject-guest-voucher">Guest - Hotel Voucher</Label>
              <Input
                id="email-subject-guest-voucher"
                v-model="form.email_subjects.guest_voucher"
                type="text"
                placeholder="Hotel Voucher: {reservation_number} - {project}"
                maxlength="120"
                @blur="save"
              />
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Sent to the guest once the hotel voucher is ready.
              </p>
            </div>

            <div class="space-y-2">
              <Label for="email-subject-guest-cancelled">Guest - Booking Cancelled</Label>
              <Input
                id="email-subject-guest-cancelled"
                v-model="form.email_subjects.guest_cancelled"
                type="text"
                placeholder="Hotel Booking Cancelled: {reservation_number} - {project}"
                maxlength="120"
                @blur="save"
              />
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Sent to the guest when their reservation is cancelled.
              </p>
            </div>

            <div class="space-y-2">
              <Label for="email-subject-staff-confirmed">Staff - Booking Confirmed</Label>
              <Input
                id="email-subject-staff-confirmed"
                v-model="form.email_subjects.staff_confirmed"
                type="text"
                placeholder="Hotel Booking Confirmed: {reservation_number} - {hotel} - {project}"
                maxlength="120"
                @blur="save"
              />
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Sent to staff recipients (above) when a booking is confirmed.
              </p>
            </div>

            <div class="space-y-2">
              <Label for="email-subject-staff-cancelled">Staff - Booking Cancelled</Label>
              <Input
                id="email-subject-staff-cancelled"
                v-model="form.email_subjects.staff_cancelled"
                type="text"
                placeholder="Hotel Booking Cancelled: {reservation_number} - {hotel} - {project}"
                maxlength="120"
                @blur="save"
              />
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                Sent to staff recipients (above) when a booking is cancelled.
              </p>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Disable Hotel Reservation Confirmation Dialog -->
    <DialogResponsive v-model:open="disableConfirmOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tight">
            Disable hotel reservation?
          </div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            This project has
            <strong
              >{{ disableActiveCount }} active reservation{{
                disableActiveCount === 1 ? "" : "s"
              }}</strong
            >
            with upcoming stays. Disabling will:
          </p>
          <ul
            class="text-muted-foreground mt-2 list-inside list-disc space-y-1 text-sm tracking-tight"
          >
            <li>Hide Hotels & Reservations tabs from staff UI for all events in this project</li>
            <li>Block customers from completing payment on pending bookings</li>
            <li>Hide hotel listings from public booking pages</li>
          </ul>
          <p class="text-muted-foreground mt-2 text-sm tracking-tight">
            Existing magic-link receipts and voucher emails remain accessible to customers.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button
              variant="outline"
              type="button"
              :disabled="hotelToggling"
              @click="disableConfirmOpen = false"
            >
              Keep enabled
            </Button>
            <Button variant="destructive" :disabled="hotelToggling" @click="confirmForceDisable">
              <Spinner v-if="hotelToggling" />
              Disable anyway
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

const props = defineProps({
  project: Object,
});

usePageMeta(null, {
  title: computed(() => `Hotel Reservations · ${props.project?.name || ""}`),
});

const route = useRoute();
const client = useSanctumClient();

const loading = ref(true);

// ----- Booking toggle -----
const hotelEnabled = ref(false);
const hotelToggling = ref(false);
const canEnableHotel = computed(() => !!props.project?.has_active_payment_gateway);
const paymentGatewaysUrl = computed(
  () =>
    props.project?.payment_gateways_url ||
    `/projects/${route.params.username}/settings/payment-gateways`
);

watch(
  () => props.project?.hotel_reservation_enabled,
  (v) => {
    hotelEnabled.value = !!v;
  },
  { immediate: true }
);

const disableConfirmOpen = ref(false);
const disableActiveCount = ref(0);

const onToggleHotel = (next) => {
  if (hotelToggling.value) return;
  if (next && !canEnableHotel.value) {
    toast.error("Payment gateway required", {
      description: "Set up an active payment gateway on the project first.",
    });
    return;
  }
  return performToggle(next, false);
};

const performToggle = async (next, force) => {
  hotelToggling.value = true;
  const previous = hotelEnabled.value;
  hotelEnabled.value = next;
  try {
    const res = await client(`/api/projects/${route.params.username}/hotel-reservation-toggle`, {
      method: "PATCH",
      body: { enabled: next, force },
    });
    toast.success(
      res?.message || (next ? "Hotel reservation enabled" : "Hotel reservation disabled")
    );
    disableConfirmOpen.value = false;
    await refreshNuxtData(`project-dashboard-${route.params.username}`);
  } catch (err) {
    hotelEnabled.value = previous;
    const errCode = err?.data?.error_code;
    if (errCode === "PAYMENT_GATEWAY_REQUIRED") {
      toast.error("Payment gateway required", {
        description: "Set up an active payment gateway on the project first.",
      });
    } else if (errCode === "ACTIVE_RESERVATIONS_EXIST") {
      disableActiveCount.value = Number(err?.data?.active_reservations_count || 0);
      disableConfirmOpen.value = true;
    } else {
      toast.error("Failed to toggle hotel reservation", {
        description: err?.data?.message || err?.message,
      });
    }
  } finally {
    hotelToggling.value = false;
  }
};

const confirmForceDisable = () => performToggle(false, true);

// ----- Website settings (hotels + email subjects) -----
const form = ref({
  show_estimated_price_in_foreign_currency: false,
  estimated_price_currency: "USD",
  hotel_notification: {
    to: [],
    cc: [],
    bcc: [],
  },
  email_subjects: {
    guest_paid: "",
    guest_voucher: "",
    guest_cancelled: "",
    staff_confirmed: "",
    staff_cancelled: "",
  },
});

// Snapshot of the last persisted payload. Auto-save no-ops when nothing
// changed — e.g. focusing then blurring a field without editing it, or an
// empty recipient row that is filtered out before sending.
let lastSavedSnapshot = null;
let saving = false;
let savePending = false;

function buildPayload() {
  return {
    hotels: {
      show_estimated_price_in_foreign_currency: form.value.show_estimated_price_in_foreign_currency,
      estimated_price_currency: form.value.estimated_price_currency,
      notification_email: {
        to: form.value.hotel_notification.to.map((email) => email.trim()).filter(Boolean),
        cc: form.value.hotel_notification.cc.map((email) => email.trim()).filter(Boolean),
        bcc: form.value.hotel_notification.bcc.map((email) => email.trim()).filter(Boolean),
      },
    },
    email_subjects: {
      guest_paid: form.value.email_subjects.guest_paid.trim(),
      guest_voucher: form.value.email_subjects.guest_voucher.trim(),
      guest_cancelled: form.value.email_subjects.guest_cancelled.trim(),
      staff_confirmed: form.value.email_subjects.staff_confirmed.trim(),
      staff_cancelled: form.value.email_subjects.staff_cancelled.trim(),
    },
  };
}

async function load() {
  loading.value = true;
  try {
    const response = await client(`/api/projects/${route.params.username}`);
    const settings = response.data?.settings ?? {};
    const hotels = settings.website_settings?.hotels ?? {};
    const hotelNotification = hotels.notification_email ?? {};
    const emailSubjects = settings.website_settings?.email_subjects ?? {};

    hotelEnabled.value = !!response.data?.hotel_reservation_enabled;

    form.value = {
      show_estimated_price_in_foreign_currency:
        hotels.show_estimated_price_in_foreign_currency ?? false,
      estimated_price_currency: hotels.estimated_price_currency ?? "USD",
      hotel_notification: {
        to: [...(hotelNotification.to ?? [])],
        cc: [...(hotelNotification.cc ?? [])],
        bcc: [...(hotelNotification.bcc ?? [])],
      },
      email_subjects: {
        guest_paid: emailSubjects.guest_paid ?? "",
        guest_voucher: emailSubjects.guest_voucher ?? "",
        guest_cancelled: emailSubjects.guest_cancelled ?? "",
        staff_confirmed: emailSubjects.staff_confirmed ?? "",
        staff_cancelled: emailSubjects.staff_cancelled ?? "",
      },
    };
    lastSavedSnapshot = JSON.stringify(buildPayload());
  } catch (err) {
    toast.error("Failed to load hotel reservation settings");
  } finally {
    loading.value = false;
  }
}

async function save() {
  const payload = buildPayload();
  const snapshot = JSON.stringify(payload);

  // Nothing changed since the last save — skip the request entirely.
  if (snapshot === lastSavedSnapshot) {
    return;
  }

  // Serialize overlapping saves: queue one more run after the current finishes.
  if (saving) {
    savePending = true;
    return;
  }

  saving = true;
  try {
    await client(`/api/projects/${route.params.username}/website-settings`, {
      method: "PATCH",
      body: payload,
    });
    lastSavedSnapshot = snapshot;
    toast.success("Hotel reservation settings updated");
  } catch (err) {
    toast.error("Failed to save", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    saving = false;
    if (savePending) {
      savePending = false;
      save();
    }
  }
}

function removeRecipient(list, index) {
  list.splice(index, 1);
  save();
}

// Section toggles persist immediately on change. Watching only the booleans
// keeps recipient typing (saved on blur) from triggering a save per keystroke.
watch(
  () => [form.value.show_estimated_price_in_foreign_currency],
  () => save()
);

// ----- Currency picker -----
const currencyOpen = ref(false);
const currencyOptions = ref([]);

const currencyMap = computed(() => {
  const map = {};
  for (const c of currencyOptions.value) {
    map[c.code] = c;
  }
  return map;
});

function currencyName(code) {
  return currencyMap.value[code]?.name || code;
}

function currencyCountry(code) {
  return currencyMap.value[code]?.country || code.slice(0, 2).toLowerCase();
}

async function loadCurrencies() {
  try {
    const response = await client("/api/exchange-rates/currencies");
    currencyOptions.value = (response.data ?? []).filter((c) => c.code !== "IDR");
  } catch {
    // Currency list is non-critical; the picker still shows the stored code.
  }
}

onMounted(() => {
  load();
  loadCurrencies();
});
</script>

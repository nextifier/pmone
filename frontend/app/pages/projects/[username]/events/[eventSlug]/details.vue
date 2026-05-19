<template>
  <div class="mx-auto flex max-w-2xl flex-col gap-y-6">
    <div class="flex flex-col items-start">
      <NuxtLink
        :to="base"
        class="text-muted-foreground hover:text-foreground mb-2 flex items-center gap-x-1 text-sm tracking-tight transition"
      >
        <Icon name="hugeicons:arrow-left-01" class="size-4" />
        <span>Back to Overview</span>
      </NuxtLink>
      <h2 class="page-title mt-1">Edit Event Details</h2>
      <p class="page-description mt-1.5">Edit event information, poster, and status.</p>
    </div>

    <FormEvent
      v-if="event.can_edit"
      :initial-data="event"
      :loading="loading"
      :errors="errors"
      submit-text="Update Event"
      submit-loading-text="Updating.."
      @submit="handleUpdate"
    />

    <div v-else class="frame">
      <div class="frame-header">
        <div class="frame-title">Read Only</div>
      </div>
      <div class="frame-panel">
        <p class="text-muted-foreground text-sm tracking-tight">
          You don't have permission to edit this event.
        </p>
      </div>
    </div>

    <!-- Event Metadata -->
    <div v-if="event" class="frame">
      <div class="frame-header">
        <div class="frame-title">Event Metadata</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-4 sm:grid-cols-2">
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">ID</p>
            <p class="font-mono text-sm">{{ event.id }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">ULID</p>
            <p class="font-mono text-sm">{{ event.ulid }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">Slug</p>
            <p class="text-sm font-medium">{{ event.slug }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">Created</p>
            <p class="text-sm">
              {{ event.created_at ? new Date(event.created_at).toLocaleString() : "-" }}
            </p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">Last Updated</p>
            <p class="text-sm">
              {{ event.updated_at ? new Date(event.updated_at).toLocaleString() : "-" }}
            </p>
          </div>
          <div v-if="event.creator">
            <p class="text-muted-foreground text-xs sm:text-sm">Created By</p>
            <p class="text-sm">{{ event.creator.name }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Conjunction Events -->
    <EventConjunctionManager v-if="event" :event="event" />

    <!-- Hotel Reservation -->
    <div v-if="event && canEditBranding" class="frame">
      <div class="frame-header">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="frame-title flex items-center gap-x-1.5">
            <Icon name="hugeicons:hotel-01" class="size-3.5 shrink-0" />
            Hotel Reservation
          </div>
          <span
            v-if="hotelEnabled"
            class="bg-success/15 text-success-foreground inline-flex w-fit shrink-0 items-center gap-x-1 rounded-full px-2.5 py-0.5 text-xs font-medium tracking-tight sm:text-sm"
          >
            <Icon name="hugeicons:checkmark-circle-02" class="size-3.5 shrink-0" />
            Active
          </span>
          <span
            v-else
            class="bg-muted text-muted-foreground inline-flex w-fit shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium tracking-tight sm:text-sm"
          >
            Disabled
          </span>
        </div>
      </div>
      <div class="frame-panel space-y-6">
        <!-- Toggle row -->
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="flex-1 space-y-1 text-sm tracking-tight">
            <p class="font-medium">Enable booking for this event</p>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              {{
                hotelEnabled
                  ? "The Hotels and Reservations tabs are visible. Public visitors can book accommodation and admins can record manual reservations."
                  : "Turn on to expose Hotels and Reservations tabs, allotment management, and the public booking flow for this event."
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

        <template v-if="hotelEnabled">
          <!-- Custom branding sub-section -->
          <div class="space-y-4 border-t pt-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="flex-1 space-y-1 text-sm tracking-tight">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                  <p class="font-medium">Custom branding for PDFs</p>
                  <span
                    v-if="brandingEnabled"
                    class="bg-info/15 text-info-foreground rounded-full px-2 py-0.5 text-xs font-medium tracking-tight sm:text-sm"
                  >
                    Override active
                  </span>
                </div>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  {{
                    brandingEnabled
                      ? "This event uses its own logo, company info, and footer on Invoice and Receipt PDFs."
                      : "Invoice and Receipt PDFs fall back to global branding. Enable to override for white-label events."
                  }}
                </p>
              </div>
              <Switch v-model="brandingEnabled" />
            </div>

            <BrandingForm
              v-if="brandingEnabled && !brandingLoading"
              :model-value="brandingDraft"
              :saving="brandingSaving"
              submit-label="Save Branding"
              @submit="handleBrandingSubmit"
            />

            <div v-else-if="brandingEnabled && brandingLoading" class="flex justify-center py-4">
              <Spinner class="size-5" />
            </div>

            <div v-else-if="!brandingEnabled && brandingDraft?.company_name" class="flex justify-end">
              <Button variant="outline" :disabled="brandingSaving" @click="clearBranding">
                <Icon v-if="brandingSaving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
                Clear Custom Branding
              </Button>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- Danger Zone -->
    <div v-if="event && event.can_delete" class="frame border-destructive/30">
      <div class="frame-header">
        <div class="frame-title text-destructive">Danger Zone</div>
      </div>
      <div class="frame-panel">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium tracking-tight">Delete Event</p>
            <p class="text-muted-foreground text-xs tracking-tight text-balance sm:text-sm">
              Move this event to trash. It can be restored later.
            </p>
          </div>
          <button
            type="button"
            :disabled="deleteLoading"
            @click="deleteDialogOpen = true"
            class="bg-destructive hover:bg-destructive/80 flex shrink-0 items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white transition disabled:opacity-50"
          >
            <Spinner v-if="deleteLoading" />
            {{ deleteLoading ? "Deleting.." : "Delete Event" }}
          </button>
        </div>
      </div>
    </div>

    <!-- Disable Hotel Reservation Confirmation Dialog -->
    <DialogResponsive v-model:open="disableConfirmOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">
            Disable hotel reservation?
          </div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            This event has
            <strong>{{ disableActiveCount }} active reservation{{
              disableActiveCount === 1 ? "" : "s"
            }}</strong>
            with upcoming stays. Disabling will:
          </p>
          <ul class="text-muted-foreground mt-2 list-inside list-disc space-y-1 text-sm tracking-tight">
            <li>Hide Hotels & Reservations tabs from staff UI for this event</li>
            <li>Block customers from completing payment on pending bookings</li>
            <li>Hide hotel listings from public booking pages</li>
          </ul>
          <p class="text-muted-foreground mt-2 text-sm tracking-tight">
            Existing magic-link receipts and voucher emails remain accessible to customers.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" type="button" :disabled="hotelToggling" @click="disableConfirmOpen = false">
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

    <!-- Delete Confirmation Dialog -->
    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Are you sure?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            This will move the event to trash. It can be restored later.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              :disabled="deleteLoading"
              @click="deleteDialogOpen = false"
            >
              Cancel
            </button>
            <button
              class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="deleteLoading"
              @click="handleDelete"
            >
              <Spinner v-if="deleteLoading" class="size-4 text-white" />
              <span v-else>Delete Event</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import BrandingForm from "@/components/branding/BrandingForm.vue";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();
const router = useRouter();

const client = useSanctumClient();
const loading = ref(false);
const errors = ref({});
const deleteLoading = ref(false);
const deleteDialogOpen = ref(false);

const base = computed(() => `/projects/${route.params.username}/events/${route.params.eventSlug}`);

// Branding override
const { hasPermission } = usePermission();
const canEditBranding = computed(() => hasPermission("events.update_branding"));

const brandingEnabled = ref(false);
const brandingDraft = ref({});
const brandingLoading = ref(false);
const brandingSaving = ref(false);

// Hotel reservation toggle
const hotelEnabled = ref(false);
const hotelToggling = ref(false);
const canEnableHotel = computed(() => !!props.project?.has_active_payment_gateway);
const paymentGatewaysUrl = computed(
  () => props.project?.payment_gateways_url || `/projects/${route.params.username}/settings/payment-gateways`
);

watch(
  () => props.event?.hotel_reservation_enabled,
  (v) => {
    hotelEnabled.value = !!v;
  },
  { immediate: true }
);

const refreshEvent = inject("refreshEvent", null);

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
    const res = await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/hotel-reservation-toggle`,
      {
        method: "PATCH",
        body: { enabled: next, force },
      }
    );
    toast.success(res?.message || (next ? "Hotel reservation enabled" : "Hotel reservation disabled"));
    disableConfirmOpen.value = false;
    if (typeof refreshEvent === "function") {
      await refreshEvent();
    } else {
      await refreshNuxtData(`event-${route.params.username}-${route.params.eventSlug}`);
    }
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

watch(
  () => props.event?.id,
  async (eventId) => {
    if (!eventId || !canEditBranding.value) return;
    brandingLoading.value = true;
    try {
      const res = await client(`/api/events/${eventId}/branding`);
      const value = res?.branding;
      brandingEnabled.value = value !== null && value !== undefined;
      brandingDraft.value = value ?? {};
    } catch (err) {
      // silent - allow user to enable later
    } finally {
      brandingLoading.value = false;
    }
  },
  { immediate: true }
);

const handleBrandingSubmit = async (payload) => {
  brandingSaving.value = true;
  try {
    const { tmp_logo, delete_logo, ...branding } = payload;
    const res = await client(`/api/events/${props.event.id}/branding`, {
      method: "PUT",
      body: { branding, tmp_logo, delete_logo },
    });
    brandingDraft.value = res?.branding ?? branding;
    brandingEnabled.value = true;
    toast.success("Event branding saved");
  } catch (err) {
    toast.error("Failed to save branding", { description: err?.data?.message || err?.message });
  } finally {
    brandingSaving.value = false;
  }
};

const clearBranding = async () => {
  brandingSaving.value = true;
  try {
    await client(`/api/events/${props.event.id}/branding`, {
      method: "PUT",
      body: { branding: null },
    });
    brandingDraft.value = {};
    toast.success("Custom branding cleared. Using global branding.");
  } catch (err) {
    toast.error("Failed to clear branding", { description: err?.data?.message || err?.message });
  } finally {
    brandingSaving.value = false;
  }
};

async function handleUpdate(payload) {
  loading.value = true;
  errors.value = {};

  try {
    await client(`/api/projects/${route.params.username}/events/${route.params.eventSlug}`, {
      method: "PUT",
      body: payload,
    });

    toast.success("Event updated successfully");

    // Refresh parent event data
    await refreshNuxtData(`event-${route.params.username}-${route.params.eventSlug}`);
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response._data?.errors || {};
    } else {
      toast.error(error.response?._data?.message || "Failed to update event");
    }
  } finally {
    loading.value = false;
  }
}

async function handleDelete() {
  deleteLoading.value = true;

  try {
    await client(`/api/projects/${route.params.username}/events/${route.params.eventSlug}`, {
      method: "DELETE",
    });

    toast.success("Event deleted");
    router.push(`/projects/${route.params.username}`);
  } catch (error) {
    toast.error(error.response?._data?.message || "Failed to delete event");
  } finally {
    deleteLoading.value = false;
  }
}

usePageMeta(null, {
  title: computed(() => `Edit Details · ${props.event?.title || "Event"}`),
});
</script>

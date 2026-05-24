<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h2 class="page-title">Website Settings</h2>
      <p class="page-description">
        Control how the public website renders sections sourced from this project. Changes are saved automatically.
      </p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner class="size-5" />
    </div>

    <div v-else class="flex flex-col gap-y-4">
      <div class="frame">
        <div class="flex items-center gap-x-3 px-4 py-3 lg:px-5">
          <Icon name="hugeicons:time-schedule" class="size-5" />
          <div class="min-w-0">
            <h3 class="text-base font-semibold tracking-tight">Rundown</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Configure how the Rundown section behaves on the public event website.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y">
          <div class="flex items-start justify-between gap-4 px-4 py-4 lg:px-5">
            <div class="space-y-1">
              <Label
                for="rundown-show-on-home"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Display Rundown section in the Home page
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                When on, the home page of the public event website includes the Rundown section.
              </p>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                For private testing, add
                <code class="bg-muted text-foreground rounded-md px-1.5 py-0.5 font-mono">?show-rundown=true</code>
                to the home page URL to force-show this section even while this toggle is off.
              </p>
            </div>
            <Switch id="rundown-show-on-home" v-model="form.show_rundown_on_home_page" />
          </div>

          <div class="flex items-start justify-between gap-4 px-4 py-4 lg:px-5">
            <div class="space-y-1">
              <Label
                for="rundown-show-search"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show search bar on Rundown section
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                Visitors can filter rundown items by typing a keyword.
              </p>
            </div>
            <Switch id="rundown-show-search" v-model="form.show_search_bar" />
          </div>

          <div class="flex items-start justify-between gap-4 px-4 py-4 lg:px-5">
            <div class="space-y-1">
              <Label
                for="rundown-show-location-filter"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show location filter on Rundown section
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                Visitors can filter rundown items by venue or room.
              </p>
            </div>
            <Switch id="rundown-show-location-filter" v-model="form.show_location_filter" />
          </div>

          <div class="flex items-start justify-between gap-4 px-4 py-4 lg:px-5">
            <div class="space-y-1">
              <Label
                for="rundown-show-all-details"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show all rundown details without click
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                Render description, speakers, and panelists inline instead of behind a click.
              </p>
            </div>
            <Switch id="rundown-show-all-details" v-model="form.show_all_rundown_details" />
          </div>
        </div>
      </div>

      <div class="frame">
        <div class="flex items-center gap-x-3 px-4 py-3 lg:px-5">
          <Icon name="hugeicons:store-04" class="size-5" />
          <div class="min-w-0">
            <h3 class="text-base font-semibold tracking-tight">Brands</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Configure how the Brands section behaves on the public event website.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y">
          <div class="flex items-start justify-between gap-4 px-4 py-4 lg:px-5">
            <div class="space-y-1">
              <Label
                for="brands-show-preview-on-home"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show Brand Preview section in the Home page
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                When on, the home page of the public event website includes a Brand Preview carousel.
              </p>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                For private testing, add
                <code class="bg-muted text-foreground rounded-md px-1.5 py-0.5 font-mono">?show-brands=true</code>
                to the home page URL to force-show this section even while this toggle is off.
              </p>
            </div>
            <Switch
              id="brands-show-preview-on-home"
              v-model="form.show_brand_preview_on_home_page"
            />
          </div>
        </div>
      </div>

      <div class="frame">
        <div class="flex items-center gap-x-3 px-4 py-3 lg:px-5">
          <Icon name="hugeicons:hotel-01" class="size-5" />
          <div class="min-w-0">
            <h3 class="text-base font-semibold tracking-tight">Hotel Reservation</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Configure the Hotels section and booking notification emails.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y">
          <div class="flex items-start justify-between gap-4 px-4 py-4 lg:px-5">
            <div class="space-y-1">
              <Label
                for="hotels-show-section-on-home"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show Hotel section in the Home page
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                When on, the home page of the public event website includes the Hotels section.
              </p>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                For private testing, add
                <code class="bg-muted text-foreground rounded-md px-1.5 py-0.5 font-mono">?show-hotel=true</code>
                to the home page URL to force-show this section even while this toggle is off.
              </p>
            </div>
            <Switch
              id="hotels-show-section-on-home"
              v-model="form.show_hotel_section_on_home_page"
            />
          </div>

          <div class="px-4 py-4 lg:px-5">
            <div class="grid grid-cols-1 gap-y-6">
              <div class="space-y-1">
                <h4 class="text-sm font-medium tracking-tight">Booking notification email</h4>
                <p class="text-muted-foreground text-xs tracking-tight">
                  Staff recipients emailed when a hotel booking is confirmed or cancelled.
                </p>
              </div>

              <div class="space-y-3">
                <Label>To (Recipients)</Label>
                <p class="text-muted-foreground text-xs tracking-tight">
                  Primary staff recipients for booking notifications
                </p>
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
                <Label>CC (Carbon Copy)</Label>
                <p class="text-muted-foreground text-xs tracking-tight">Optional CC recipients</p>
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
                <Label>BCC (Blind Carbon Copy)</Label>
                <p class="text-muted-foreground text-xs tracking-tight">Optional BCC recipients</p>
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

              <div class="space-y-4 border-t pt-5">
                <div class="space-y-1">
                  <h4 class="text-sm font-medium tracking-tight">Email subject templates</h4>
                  <p class="text-muted-foreground text-xs tracking-tight">
                    Customize the subject for each hotel reservation email.
                    Leave a field blank to use the default. Placeholders:
                    <code class="bg-muted text-foreground rounded px-1 py-0.5 font-mono">{reservation_number}</code>,
                    <code class="bg-muted text-foreground rounded px-1 py-0.5 font-mono">{hotel}</code>,
                    <code class="bg-muted text-foreground rounded px-1 py-0.5 font-mono">{event}</code>,
                    <code class="bg-muted text-foreground rounded px-1 py-0.5 font-mono">{guest}</code>,
                    <code class="bg-muted text-foreground rounded px-1 py-0.5 font-mono">{project}</code>,
                    <code class="bg-muted text-foreground rounded px-1 py-0.5 font-mono">{status}</code> (staff only).
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="email-subject-guest-paid">Guest — Booking Confirmed</Label>
                  <Input
                    id="email-subject-guest-paid"
                    v-model="form.email_subjects.guest_paid"
                    type="text"
                    placeholder="Hotel Booking Confirmed: {reservation_number} - {project}"
                    maxlength="120"
                    @blur="save"
                  />
                  <p class="text-muted-foreground text-xs tracking-tight">
                    Sent to the guest after their payment is received.
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="email-subject-guest-voucher">Guest — Hotel Voucher</Label>
                  <Input
                    id="email-subject-guest-voucher"
                    v-model="form.email_subjects.guest_voucher"
                    type="text"
                    placeholder="Hotel Voucher: {reservation_number} - {project}"
                    maxlength="120"
                    @blur="save"
                  />
                  <p class="text-muted-foreground text-xs tracking-tight">
                    Sent to the guest once the hotel voucher is ready.
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="email-subject-guest-cancelled">Guest — Booking Cancelled</Label>
                  <Input
                    id="email-subject-guest-cancelled"
                    v-model="form.email_subjects.guest_cancelled"
                    type="text"
                    placeholder="Hotel Booking Cancelled: {reservation_number} - {project}"
                    maxlength="120"
                    @blur="save"
                  />
                  <p class="text-muted-foreground text-xs tracking-tight">
                    Sent to the guest when their reservation is cancelled.
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="email-subject-staff-confirmed">Staff — Booking Confirmed</Label>
                  <Input
                    id="email-subject-staff-confirmed"
                    v-model="form.email_subjects.staff_confirmed"
                    type="text"
                    placeholder="Hotel Booking Confirmed: {reservation_number} - {hotel} - {project}"
                    maxlength="120"
                    @blur="save"
                  />
                  <p class="text-muted-foreground text-xs tracking-tight">
                    Sent to staff recipients (above) when a booking is confirmed.
                  </p>
                </div>

                <div class="space-y-2">
                  <Label for="email-subject-staff-cancelled">Staff — Booking Cancelled</Label>
                  <Input
                    id="email-subject-staff-cancelled"
                    v-model="form.email_subjects.staff_cancelled"
                    type="text"
                    placeholder="Hotel Booking Cancelled: {reservation_number} - {hotel} - {project}"
                    maxlength="120"
                    @blur="save"
                  />
                  <p class="text-muted-foreground text-xs tracking-tight">
                    Sent to staff recipients (above) when a booking is cancelled.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

const props = defineProps({
  project: Object,
});

usePageMeta(null, {
  title: computed(() => `Website Settings · ${props.project?.name || ""}`),
});

const route = useRoute();
const client = useSanctumClient();

const loading = ref(true);

const form = ref({
  show_rundown_on_home_page: false,
  show_search_bar: true,
  show_location_filter: true,
  show_all_rundown_details: false,
  show_brand_preview_on_home_page: false,
  show_hotel_section_on_home_page: false,
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
    rundown: {
      show_rundown_on_home_page: form.value.show_rundown_on_home_page,
      show_search_bar: form.value.show_search_bar,
      show_location_filter: form.value.show_location_filter,
      show_all_rundown_details: form.value.show_all_rundown_details,
    },
    brands: {
      show_brand_preview_on_home_page: form.value.show_brand_preview_on_home_page,
    },
    hotels: {
      show_hotel_section_on_home_page: form.value.show_hotel_section_on_home_page,
      notification_email: {
        to: form.value.hotel_notification.to
          .map((email) => email.trim())
          .filter(Boolean),
        cc: form.value.hotel_notification.cc
          .map((email) => email.trim())
          .filter(Boolean),
        bcc: form.value.hotel_notification.bcc
          .map((email) => email.trim())
          .filter(Boolean),
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
    const rundown = settings.website_settings?.rundown ?? {};
    const brands = settings.website_settings?.brands ?? {};
    const hotels = settings.website_settings?.hotels ?? {};
    const hotelNotification = hotels.notification_email ?? {};
    const emailSubjects = settings.website_settings?.email_subjects ?? {};

    form.value = {
      show_rundown_on_home_page: rundown.show_rundown_on_home_page ?? false,
      show_search_bar: rundown.show_search_bar ?? true,
      show_location_filter: rundown.show_location_filter ?? true,
      show_all_rundown_details: rundown.show_all_rundown_details ?? false,
      show_brand_preview_on_home_page:
        brands.show_brand_preview_on_home_page ?? false,
      show_hotel_section_on_home_page:
        hotels.show_hotel_section_on_home_page ?? false,
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
    toast.error("Failed to load website settings");
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
    toast.success("Website settings updated");
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
  () => [
    form.value.show_rundown_on_home_page,
    form.value.show_search_bar,
    form.value.show_location_filter,
    form.value.show_all_rundown_details,
    form.value.show_brand_preview_on_home_page,
    form.value.show_hotel_section_on_home_page,
  ],
  () => save(),
);

onMounted(load);
</script>

<template>
  <div>
    <!-- Loading -->
    <div v-if="pending && !profile" class="space-y-6">
      <Skeleton class="h-24 w-full rounded-xl" />
      <Skeleton class="h-96 w-full rounded-xl" />
    </div>

    <!-- Error -->
    <Empty v-else-if="error" class="border">
      <EmptyMedia variant="icon">
        <Icon name="hugeicons:alert-02" class="text-destructive size-6" />
      </EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>Couldn't load your profile</EmptyTitle>
        <EmptyDescription>Something went wrong. Please try again.</EmptyDescription>
      </EmptyHeader>
      <Button variant="outline" size="sm" @click="fetchProfile">
        <Icon name="hugeicons:reload" class="size-4" />
        <span>Try again</span>
      </Button>
    </Empty>

    <div v-else class="space-y-8">
      <!-- Completeness meter -->
      <div class="border-border bg-card rounded-xl border p-4 sm:p-5">
        <div class="flex items-center justify-between gap-x-3">
          <p class="text-sm font-medium tracking-tight">Profile completeness</p>
          <span class="text-sm font-medium tabular-nums tracking-tight">
            {{ completeness }}%
          </span>
        </div>
        <Progress :model-value="completeness" class="mt-3" />
        <p class="text-muted-foreground mt-2 text-xs tracking-tight sm:text-sm">
          {{
            completeness >= 100
              ? "Your profile is complete. You're all set for business matching."
              : "Complete your profile to get the most out of business matching at events."
          }}
        </p>
      </div>

      <!-- Profile form -->
      <form class="grid gap-y-8" autocomplete="off" @submit.prevent="handleSubmit">
        <div class="frame">
          <div class="frame-header">
            <div class="frame-title">Personal Information</div>
          </div>
          <div class="frame-panel">
            <div class="grid grid-cols-1 gap-x-2 gap-y-6 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="name">Full Name</Label>
                <Input id="name" v-model="form.name" type="text" required autocomplete="off" />
                <InputErrorMessage :errors="errors.name" />
              </div>

              <div class="space-y-2">
                <Label for="email">Email</Label>
                <Input id="email" :model-value="profile?.email" type="email" disabled readonly />
                <p class="text-muted-foreground text-xs tracking-tight">
                  Email cannot be changed here.
                </p>
              </div>

              <div class="space-y-2">
                <Label for="phone">Phone</Label>
                <InputPhone id="phone" v-model="form.phone" />
                <InputErrorMessage :errors="errors.phone" />
              </div>

              <div class="space-y-2">
                <Label for="gender">Gender</Label>
                <Select v-model="form.gender">
                  <SelectTrigger id="gender" class="w-full">
                    <SelectValue placeholder="Select gender" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="male">Male</SelectItem>
                    <SelectItem value="female">Female</SelectItem>
                    <SelectItem value="other">Other</SelectItem>
                  </SelectContent>
                </Select>
                <InputErrorMessage :errors="errors.gender" />
              </div>

              <div class="space-y-2">
                <Label for="birth_date">Birth Date</Label>
                <DatePicker
                  v-model="form.birth_date"
                  disable-future-dates
                  placeholder="Pick your birth date"
                />
                <InputErrorMessage :errors="errors.birth_date" />
              </div>
            </div>
          </div>
        </div>

        <div class="frame">
          <div class="frame-header">
            <div class="frame-title">Location</div>
          </div>
          <div class="frame-panel">
            <div class="grid grid-cols-1 gap-x-2 gap-y-6 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="country">Country</Label>
                <LocationCombobox
                  v-model="form.country"
                  :options="countries"
                  :pinned="['Indonesia']"
                  placeholder="Select country"
                />
                <InputErrorMessage :errors="errors.country" />
              </div>

              <div class="space-y-2">
                <Label for="city">City</Label>
                <Input id="city" v-model="form.city" type="text" autocomplete="off" />
                <InputErrorMessage :errors="errors.city" />
              </div>
            </div>
          </div>
        </div>

        <div class="frame">
          <div class="frame-header">
            <div class="frame-title">Professional</div>
          </div>
          <div class="frame-panel">
            <div class="grid grid-cols-1 gap-x-2 gap-y-6 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="company_name">Company</Label>
                <Input id="company_name" v-model="form.company_name" type="text" autocomplete="off" />
                <InputErrorMessage :errors="errors.company_name" />
              </div>

              <div class="space-y-2">
                <Label for="position">Position</Label>
                <Input id="position" v-model="form.position" type="text" autocomplete="off" />
                <InputErrorMessage :errors="errors.position" />
              </div>

              <div class="space-y-2 sm:col-span-2">
                <Label for="profession">Profession</Label>
                <Input id="profession" v-model="form.profession" type="text" autocomplete="off" />
                <InputErrorMessage :errors="errors.profession" />
              </div>
            </div>
          </div>
        </div>

        <div class="frame">
          <div class="frame-header">
            <div class="frame-title">Business Matching</div>
            <div class="frame-description">
              Let event organizers and other attendees discover you for networking.
            </div>
          </div>
          <div class="frame-panel">
            <div class="flex items-center justify-between gap-x-3">
              <div class="space-y-0.5">
                <p class="text-sm font-medium tracking-tight">
                  Opt in to business matching
                </p>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  Your profile becomes visible to the matching directory.
                </p>
              </div>
              <Switch v-model="form.business_matching_opt_in" aria-label="Business matching opt in" />
            </div>
          </div>
        </div>

        <div class="flex justify-end">
          <Button type="submit" :disabled="saving">
            <Spinner v-if="saving" />
            <span>Save profile</span>
          </Button>
        </div>
      </form>

      <!-- Business Matching intake, per held event -->
      <div v-if="hasResolvableEvents" class="space-y-4">
        <div class="space-y-1">
          <h2 class="text-lg font-medium tracking-tighter">Event business matching</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Answer matching questions for each event you have a ticket for.
          </p>
        </div>

        <BusinessMatchingIntake
          v-for="event in resolvableEvents"
          :key="event.id"
          :event-id="event.id"
          :event-title="event.title"
          @opt-in-change="onIntakeOptInChange"
        />
      </div>

      <!-- Held events without a resolvable numeric id (BM intake gated) -->
      <div
        v-else-if="heldEvents.length"
        class="border-border bg-muted/30 rounded-xl border border-dashed p-4 text-center sm:p-5"
      >
        <p class="text-muted-foreground text-sm tracking-tight">
          Business matching questions for your events will appear here once available.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import BusinessMatchingIntake from "@/components/BusinessMatchingIntake.vue";
import countries from "@/data/countries.json";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { InputErrorMessage } from "@/components/ui/input-error-message";
import { LocationCombobox } from "@/components/ui/location-combobox";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const client = useSanctumClient();

const profile = ref(null);
const pending = ref(true);
const error = ref(null);
const saving = ref(false);
const errors = ref({});

const form = reactive({
  name: "",
  phone: "",
  gender: "",
  birth_date: null,
  country: "",
  city: "",
  company_name: "",
  profession: "",
  position: "",
  business_matching_opt_in: false,
});

const completeness = computed(() => Number(profile.value?.profile_completeness ?? 0));

const parseBirthDate = (value) => {
  if (!value) return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
  if (!match) return null;
  const [, y, m, d] = match;
  return new Date(Number(y), Number(m) - 1, Number(d));
};

const formatBirthDate = (date) => {
  if (!date) return null;
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
};

const fetchProfile = async () => {
  pending.value = true;
  error.value = null;
  try {
    const response = await client("/api/my/ticket-profile");
    const data = response?.data || {};
    profile.value = data;
    form.name = data.name || "";
    form.phone = data.phone || "";
    form.gender = data.gender || "";
    form.birth_date = parseBirthDate(data.birth_date);
    form.country = data.country || "";
    form.city = data.city || "";
    form.company_name = data.company_name || "";
    form.profession = data.profession || "";
    form.position = data.position || "";
    form.business_matching_opt_in = !!data.business_matching_opt_in;
  } catch (err) {
    error.value = err;
  } finally {
    pending.value = false;
  }
};

const handleSubmit = async () => {
  saving.value = true;
  errors.value = {};
  try {
    const response = await client("/api/my/ticket-profile", {
      method: "PATCH",
      body: {
        name: form.name,
        phone: form.phone || null,
        gender: form.gender || null,
        birth_date: formatBirthDate(form.birth_date),
        country: form.country || null,
        city: form.city || null,
        company_name: form.company_name || null,
        profession: form.profession || null,
        position: form.position || null,
        business_matching_opt_in: form.business_matching_opt_in,
      },
    });
    profile.value = response?.data || profile.value;
    toast.success(response?.message || "Profile updated");
  } catch (err) {
    const status = err?.response?.status || err?.statusCode;
    const message = err?.response?._data?.message || err?.data?.message;
    if (status === 422 && err?.response?._data?.errors) {
      errors.value = err.response._data.errors;
    }
    toast.error(message || "Failed to update profile");
  } finally {
    saving.value = false;
  }
};

const onIntakeOptInChange = (value) => {
  form.business_matching_opt_in = value;
  if (profile.value) {
    profile.value.business_matching_opt_in = value;
  }
};

/**
 * Distinct events the user holds tickets for. The /api/my/tickets payload only
 * exposes { slug, title }; the business matching field-responses endpoint binds
 * Event by its numeric id. If the payload gains an `id`, intake activates
 * automatically. Until then only the gated placeholder is shown.
 */
const heldEvents = ref([]);

const fetchHeldEvents = async () => {
  try {
    const response = await client("/api/my/tickets");
    const seen = new Map();
    for (const item of response?.data || []) {
      const event = item.event;
      if (!event) continue;
      const key = event.id ?? event.slug;
      if (key == null || seen.has(key)) continue;
      seen.set(key, {
        id: event.id ?? null,
        slug: event.slug ?? null,
        title: event.title ?? "",
      });
    }
    heldEvents.value = Array.from(seen.values());
  } catch (err) {
    console.error("Failed to load held events for business matching:", err);
  }
};

const resolvableEvents = computed(() =>
  heldEvents.value.filter((event) => event.id != null)
);

const hasResolvableEvents = computed(() => resolvableEvents.value.length > 0);

onMounted(() => {
  fetchProfile();
  fetchHeldEvents();
});

usePageMeta(null, { title: "My Profile" });
</script>

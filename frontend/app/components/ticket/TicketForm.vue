<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8">
    <!-- Basic Information -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Basic Information</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="ticket-kind">Kind</Label>
            <Select
              :model-value="form.kind"
              @update:model-value="(v) => v && (form.kind = v)"
            >
              <SelectTrigger id="ticket-kind" class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="entry">Entry - admits to the event</SelectItem>
                <SelectItem value="add_on">Add-on - sessions, extras, merchandise</SelectItem>
              </SelectContent>
            </Select>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Entry tickets control event admission by day. Add-on tickets can host sessions.
            </p>
            <InputErrorMessage :errors="errors.kind" />
          </div>

          <!-- Translatable title -->
          <div class="space-y-2">
            <Label>Title</Label>
            <Tabs v-model="activeLocale" variant="segmented">
              <TabsList>
                <TabsIndicator />
                <TabsTrigger v-for="locale in LOCALES" :key="locale.value" :value="locale.value">
                  {{ locale.label }}
                </TabsTrigger>
              </TabsList>
            </Tabs>
            <Input
              v-model="titleField"
              :required="activeLocale === 'en'"
              :placeholder="activeLocale === 'en' ? 'Ticket title' : 'Judul tiket'"
            />
            <InputErrorMessage :errors="localizedTitleErrors" />
          </div>

          <div class="space-y-2">
            <Label for="ticket-tier">Tier</Label>
            <Input id="ticket-tier" v-model="form.tier" placeholder="VIP / Regular / Early Bird" />
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Optional grouping label shown alongside the ticket.
            </p>
            <InputErrorMessage :errors="errors.tier" />
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="ticket-day-pass">Day pass label</Label>
              <Input id="ticket-day-pass" v-model="form.day_pass" placeholder="All-day pass / One-day pass" />
              <InputErrorMessage :errors="errors['more_details.day_pass']" />
            </div>
            <div class="space-y-2">
              <Label for="ticket-entrance">Entrance label</Label>
              <Input id="ticket-entrance" v-model="form.entrance" placeholder="Regular entrance / VIP entrance" />
              <InputErrorMessage :errors="errors['more_details.entrance']" />
            </div>
          </div>
          <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
            Optional badges shown on the public ticket card. Leave empty to hide.
          </p>

          <div class="space-y-2">
            <Label>Benefits</Label>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Type and press Enter to add. Examples: Lunch included, Priority seating, Welcome kit.
            </p>
            <TagsInput v-model="form.benefits" class="text-sm">
              <TagsInputItem v-for="tag in form.benefits" :key="tag" :value="tag">
                <TagsInputItemText />
                <TagsInputItemDelete />
              </TagsInputItem>
              <TagsInputInput placeholder="Add benefit..." />
            </TagsInput>
            <InputErrorMessage :errors="errors.benefits" />
          </div>
        </div>
      </div>
    </div>

    <!-- Purchase -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Purchase</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="ticket-purchase-type">Purchase type</Label>
            <Select
              :model-value="form.purchase_type"
              @update:model-value="(v) => v && (form.purchase_type = v)"
            >
              <SelectTrigger id="ticket-purchase-type" class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="first_party">First-party - sold on this platform</SelectItem>
                <SelectItem value="external">External - link to another store</SelectItem>
              </SelectContent>
            </Select>
            <InputErrorMessage :errors="errors.purchase_type" />
          </div>

          <div v-if="form.purchase_type === 'external'" class="space-y-2">
            <Label for="ticket-external-url">External URL</Label>
            <Input
              id="ticket-external-url"
              v-model="form.external_url"
              type="url"
              placeholder="https://tickets.example.com/..."
              required
            />
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Buyers are redirected here instead of checking out on the event website.
            </p>
            <InputErrorMessage :errors="errors.external_url" />
          </div>

          <div class="space-y-2">
            <Label for="ticket-currency">Currency</Label>
            <Input
              id="ticket-currency"
              v-model="form.currency"
              maxlength="3"
              class="max-w-32"
              placeholder="IDR"
            />
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              3-letter ISO currency code. Defaults to IDR.
            </p>
            <InputErrorMessage :errors="errors.currency" />
          </div>
        </div>
      </div>
    </div>

    <!-- Inventory -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Inventory &amp; Quantity</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="ticket-stock">Stock</Label>
            <InputNumber id="ticket-stock" v-model="form.stock" :min="0" placeholder="Unlimited" />
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Total tickets available. Leave empty for unlimited stock.
            </p>
            <InputErrorMessage :errors="errors.stock" />
          </div>

          <div class="grid grid-cols-2 gap-x-2 gap-y-6">
            <div class="space-y-2">
              <Label for="ticket-min-qty">Min quantity per order</Label>
              <InputNumber id="ticket-min-qty" v-model="form.min_quantity" :min="1" />
              <InputErrorMessage :errors="errors.min_quantity" />
            </div>
            <div class="space-y-2">
              <Label for="ticket-max-qty">Max quantity per order</Label>
              <InputNumber
                id="ticket-max-qty"
                v-model="form.max_quantity"
                :min="1"
                placeholder="No limit"
              />
              <InputErrorMessage :errors="errors.max_quantity" />
            </div>
          </div>

          <div
            v-if="form.kind === 'add_on'"
            class="flex items-center justify-between gap-3"
          >
            <div class="space-y-1">
              <Label for="ticket-print-on-redeem" class="cursor-pointer">Print on redeem</Label>
              <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
                Print a physical pass when this add-on is redeemed on-site.
              </p>
            </div>
            <Switch id="ticket-print-on-redeem" v-model="form.print_on_redeem" />
          </div>
        </div>
      </div>
    </div>

    <!-- Visibility / access -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Visibility</div>
        <div class="frame-description">
          Control who can see and buy this ticket. Gated tickets require a valid access code at
          checkout (managed in the Access Codes tab).
        </div>
      </div>
      <div class="frame-panel">
        <div class="space-y-2">
          <Label for="ticket-visibility">Who can buy this</Label>
          <Select
            :model-value="form.visibility"
            @update:model-value="(v) => v && (form.visibility = v)"
          >
            <SelectTrigger id="ticket-visibility" class="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="public">Public - listed and open to everyone</SelectItem>
              <SelectItem value="code_required">
                Code required - listed but locked until a code is entered
              </SelectItem>
              <SelectItem value="hidden">
                Hidden - not listed; revealed only by a valid access code
              </SelectItem>
            </SelectContent>
          </Select>
          <InputErrorMessage :errors="errors.visibility" />
        </div>
      </div>
    </div>

    <!-- Valid Days (entry only) -->
    <div v-if="form.kind === 'entry'" class="frame">
      <div class="frame-header">
        <div class="frame-title">Valid Days</div>
        <div class="frame-description">
          Which event days this ticket admits. Leave all unchecked to admit on every day.
        </div>
      </div>
      <div class="frame-panel">
        <div class="mb-4 flex items-center justify-between gap-3 border-b pb-4">
          <div class="space-y-1">
            <Label for="ticket-requires-day" class="cursor-pointer">Let buyer pick a day</Label>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              On (day pass): the buyer picks one valid day at checkout. Off (bundle): the ticket admits on every valid day selected below.
            </p>
          </div>
          <Switch id="ticket-requires-day" v-model="form.requires_day_selection" />
        </div>
        <div v-if="daysLoading" class="flex justify-center py-6">
          <Spinner class="size-5" />
        </div>
        <div
          v-else-if="!selectableDays.length"
          class="text-muted-foreground rounded-md border border-dashed py-6 text-center text-sm tracking-tight"
        >
          No event days yet. Generate them in Ticket Settings first.
        </div>
        <div v-else>
          <ToggleGroup
            type="multiple"
            variant="pill"
            :model-value="form.valid_days"
            @update:model-value="(v) => (form.valid_days = Array.isArray(v) ? [...v] : [])"
          >
            <ToggleGroupItem v-for="day in selectableDays" :key="day.id" :value="day.id">
              {{ dayLabel(day) }}
            </ToggleGroupItem>
          </ToggleGroup>
        </div>
        <InputErrorMessage :errors="errors.valid_days" class="mt-2" />
      </div>
    </div>

    <!-- Poster -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Poster</div>
      </div>
      <div class="frame-panel">
        <div class="space-y-2">
          <Label>Poster image</Label>
          <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
            Single image shown on ticket cards. Format JPG/PNG/WebP.
          </p>
          <InputFileImage
            v-model="posterFiles"
            v-model:delete-flag="deletePoster"
            :initial-image="initialPoster"
            container-class="relative isolate aspect-square w-full max-w-[16rem] overflow-hidden rounded-lg"
            image-class="size-full object-cover"
          />
          <InputErrorMessage :errors="errors.tmp_poster" />
        </div>
      </div>
    </div>

    <!-- Visibility -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Visibility</div>
      </div>
      <div class="frame-panel">
        <div class="flex items-center justify-between gap-3">
          <div class="space-y-1">
            <Label for="ticket-active" class="cursor-pointer">Ticket is active</Label>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Inactive tickets are hidden from the public event website and cannot be purchased.
            </p>
          </div>
          <Switch id="ticket-active" v-model="form.is_active" />
        </div>
        <InputErrorMessage :errors="errors.is_active" />
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <Button variant="outline" type="button" @click="$emit('cancel')">Cancel</Button>
      <Button type="submit" :disabled="saving">
        <Spinner v-if="saving" />
        {{ saving ? "Saving..." : submitLabel }}
      </Button>
    </div>
  </form>
</template>

<script setup>
import InputFileImage from "@/components/InputFileImage.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { InputErrorMessage } from "@/components/ui/input-error-message";
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
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { computed, nextTick, onMounted, reactive, ref, watch } from "vue";

const props = defineProps({
  event: { type: Object, required: true },
  initial: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  submitLabel: { type: String, default: "Save Ticket" },
});

const emit = defineEmits(["submit", "cancel"]);

const client = useSanctumClient();

const LOCALES = [
  { value: "en", label: "English" },
  { value: "id", label: "Indonesian" },
  { value: "ja", label: "日本語" },
  { value: "ko", label: "한국어" },
  { value: "zh", label: "中文" },
];

const EMPTY_TRANSLATABLE = () => ({ en: "", id: "", ja: "", ko: "", zh: "" });

const activeLocale = ref("en");

const form = reactive({
  kind: "entry",
  title: EMPTY_TRANSLATABLE(),
  tier: "",
  day_pass: "",
  entrance: "",
  benefits: [],
  currency: "IDR",
  purchase_type: "first_party",
  external_url: "",
  print_on_redeem: false,
  requires_day_selection: false,
  stock: null,
  min_quantity: 1,
  max_quantity: null,
  valid_days: [],
  is_active: true,
  visibility: "public",
});

const posterFiles = ref([]);
const deletePoster = ref(false);
const initialPoster = ref(null);
// Preserve any extra more_details keys (the form only manages day_pass/entrance).
const initialMoreDetails = ref({});

const titleField = computed({
  get: () => form.title[activeLocale.value] ?? "",
  set: (value) => {
    form.title = { ...form.title, [activeLocale.value]: value };
  },
});

const localizedTitleErrors = computed(
  () => props.errors[`title.${activeLocale.value}`] ?? props.errors.title ?? null
);

// --- Event days for the valid-days picker (entry tickets) ---
const eventDays = ref([]);
const daysLoading = ref(false);

// Offer active days, plus any day this ticket already references (so editing a
// ticket that points at a now-inactive day still shows it).
const selectableDays = computed(() =>
  eventDays.value.filter((day) => day.is_active || form.valid_days.includes(day.id))
);

const fetchEventDays = async () => {
  daysLoading.value = true;
  try {
    const res = await client(`/api/events/${props.event.id}/event-days`);
    eventDays.value = res?.data ?? [];
  } catch {
    eventDays.value = [];
  } finally {
    daysLoading.value = false;
  }
};

const dayLabel = (day) => {
  const l = day.label;
  const text = l && typeof l === "object" ? l.en ?? Object.values(l).find(Boolean) : l;
  return appendDayDate(text || `Day ${day.day_number}`, day?.date);
};

const toggleValidDay = (id, checked) => {
  if (checked) {
    if (!form.valid_days.includes(id)) form.valid_days.push(id);
  } else {
    form.valid_days = form.valid_days.filter((d) => d !== id);
  }
};

watch(
  () => props.initial,
  (val) => {
    if (!val) return;
    Object.assign(form, {
      kind: val.kind ?? "entry",
      title: { ...EMPTY_TRANSLATABLE(), ...(val.title ?? {}) },
      tier: val.tier ?? "",
      day_pass: val.more_details?.day_pass ?? "",
      entrance: val.more_details?.entrance ?? "",
      benefits: Array.isArray(val.benefits) ? [...val.benefits] : [],
      currency: val.currency ?? "IDR",
      purchase_type: val.purchase_type ?? "first_party",
      external_url: val.external_url ?? "",
      print_on_redeem: val.print_on_redeem ?? false,
      requires_day_selection: val.requires_day_selection ?? false,
      stock: val.stock ?? null,
      min_quantity: val.min_quantity ?? 1,
      max_quantity: val.max_quantity ?? null,
      valid_days: Array.isArray(val.valid_day_ids) ? [...val.valid_day_ids] : [],
      is_active: val.is_active ?? true,
      visibility: val.visibility ?? "public",
    });
    initialMoreDetails.value = val.more_details && typeof val.more_details === "object" ? { ...val.more_details } : {};
    const poster = Array.isArray(val.poster) ? val.poster[0] : val.poster;
    initialPoster.value = poster?.original ?? poster?.url ?? null;
    deletePoster.value = false;
    posterFiles.value = [];
  },
  { immediate: true, deep: true }
);

onMounted(fetchEventDays);

function cleanTranslatable(t) {
  const out = {};
  for (const [k, v] of Object.entries(t ?? {})) {
    out[k] = v && String(v).trim().length > 0 ? v : null;
  }
  return out;
}

const handleSubmit = () => {
  const payload = {
    kind: form.kind,
    title: cleanTranslatable(form.title),
    tier: form.tier || null,
    more_details: {
      ...initialMoreDetails.value,
      day_pass: form.day_pass?.trim() || null,
      entrance: form.entrance?.trim() || null,
    },
    benefits: form.benefits,
    currency: (form.currency || "IDR").toUpperCase(),
    purchase_type: form.purchase_type,
    external_url: form.purchase_type === "external" ? form.external_url || null : null,
    print_on_redeem: form.kind === "add_on" ? form.print_on_redeem : false,
    requires_day_selection: form.kind === "entry" ? form.requires_day_selection : false,
    stock: form.stock === "" ? null : form.stock,
    min_quantity: form.min_quantity ?? 1,
    max_quantity: form.max_quantity === "" ? null : form.max_quantity,
    // valid_days only meaningful for entry tickets; the API rejects them on add_on.
    valid_days: form.kind === "entry" ? form.valid_days : [],
    is_active: form.is_active,
    visibility: form.visibility,
  };

  const poster = posterFiles.value?.[0];
  if (poster && typeof poster === "string" && poster.startsWith("tmp-")) {
    payload.tmp_poster = poster;
  } else if (deletePoster.value) {
    payload.delete_poster = true;
  }

  emit("submit", payload);
};

// Reset deferred state when toggling away from external purchase, keeping the
// payload clean even if the user filled the URL then switched back.
watch(
  () => form.purchase_type,
  (v) => {
    if (v !== "external") form.external_url = "";
  }
);

// Activating the title locale tab should land focus on English first.
watch(activeLocale, () => nextTick());
</script>

<script setup lang="ts">
import { Time } from "@internationalized/date";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import InputFileImage from "@/components/InputFileImage.vue";
import SpeakerListInput from "@/components/SpeakerListInput.vue";
import PanelistListInput from "@/components/PanelistListInput.vue";

type Translatable = Record<string, string | null>;
type Speaker = { name: string; title?: string; organization?: string };
type Panelist = { name: string; title?: string };

type RundownItemPayload = {
  id?: number;
  date?: string | null;
  start_time?: string | null;
  end_time?: string | null;
  title?: Translatable;
  subtitle?: Translatable;
  description?: Translatable;
  theme?: Translatable;
  location?: Translatable;
  presented_by?: Translatable;
  moderator?: Translatable;
  panelists?: Panelist[] | null;
  speakers?: Speaker[] | null;
  categories?: string[];
  poster_image?: { sm?: string; md?: string; lg?: string; original?: string } | null;
  is_active?: boolean;
};

type EventRange = {
  start_date?: string | null;
  end_date?: string | null;
};

const props = withDefaults(
  defineProps<{
    item?: RundownItemPayload | null;
    defaultDate?: string | null;
    event?: EventRange | null;
    loading?: boolean;
    errors?: Record<string, string[]>;
  }>(),
  {
    item: null,
    defaultDate: null,
    event: null,
    loading: false,
    errors: () => ({}),
  }
);

const emit = defineEmits<{
  submit: [payload: Record<string, unknown>];
  cancel: [];
}>();

const LOCALES = [
  { value: "en", label: "English" },
  { value: "id", label: "Indonesian" },
] as const;

type LocaleKey = (typeof LOCALES)[number]["value"];

const activeLocale = ref<LocaleKey>("en");

const initialDate = props.item?.date ?? props.defaultDate ?? props.event?.start_date ?? null;

const form = ref({
  date: toDateOnly(initialDate),
  title: { ...(props.item?.title ?? { en: "", id: "" }) } as Translatable,
  subtitle: { ...(props.item?.subtitle ?? { en: "", id: "" }) } as Translatable,
  description: { ...(props.item?.description ?? { en: "", id: "" }) } as Translatable,
  theme: { ...(props.item?.theme ?? { en: "", id: "" }) } as Translatable,
  location: { ...(props.item?.location ?? { en: "", id: "" }) } as Translatable,
  presented_by: { ...(props.item?.presented_by ?? { en: "", id: "" }) } as Translatable,
  moderator: { ...(props.item?.moderator ?? { en: "", id: "" }) } as Translatable,
  panelists: (props.item?.panelists ?? []) as Panelist[],
  speakers: (props.item?.speakers ?? []) as Speaker[],
  categories: (props.item?.categories ?? []) as string[],
  is_active: props.item?.is_active ?? true,
});

function toDateOnly(value: string | null | undefined): string | null {
  if (!value) return null;
  return String(value).slice(0, 10);
}

const eventStart = computed<Date | null>(() => parseDateString(props.event?.start_date));
const eventEnd = computed<Date | null>(() => parseDateString(props.event?.end_date));

function parseDateString(value: string | null | undefined): Date | null {
  if (!value) return null;
  const iso = String(value).slice(0, 10);
  const [y, m, d] = iso.split("-").map(Number);
  if (!y || !m || !d) return null;
  return new Date(y, m - 1, d);
}

const dateValue = computed<Date | null>({
  get() {
    return parseDateString(form.value.date);
  },
  set(next) {
    if (!next) {
      form.value.date = null;
      return;
    }
    const y = next.getFullYear();
    const m = String(next.getMonth() + 1).padStart(2, "0");
    const d = String(next.getDate()).padStart(2, "0");
    form.value.date = `${y}-${m}-${d}`;
  },
});

const timeRange = ref<{ start: Time | undefined; end: Time | undefined }>({
  start: parseTime(props.item?.start_time),
  end: parseTime(props.item?.end_time),
});

function parseTime(input?: string | null): Time | undefined {
  if (!input) return undefined;
  const [h, m] = input.split(":").map(Number);
  if (Number.isNaN(h)) return undefined;
  return new Time(h ?? 0, m ?? 0);
}

function formatTime(time: Time | undefined): string | null {
  if (!time) return null;
  return `${String(time.hour).padStart(2, "0")}:${String(time.minute).padStart(2, "0")}`;
}

const imageFiles = ref<{ poster: string[] }>({ poster: [] });
const deleteFlags = ref<{ poster: boolean }>({ poster: false });

const initialPoster = computed(() => props.item?.poster_image ?? null);

type TranslatableKey =
  | "title"
  | "subtitle"
  | "description"
  | "theme"
  | "location"
  | "presented_by"
  | "moderator";

function bind(key: TranslatableKey) {
  return computed<string>({
    get: () => form.value[key]?.[activeLocale.value] ?? "",
    set: (value: string) => {
      form.value[key] = { ...form.value[key], [activeLocale.value]: value };
    },
  });
}

const titleField = bind("title");
const subtitleField = bind("subtitle");
const descriptionField = bind("description");
const themeField = bind("theme");
const locationField = bind("location");
const presentedByField = bind("presented_by");
const moderatorField = bind("moderator");

function localeHasContent(field: TranslatableKey, locale: LocaleKey): boolean {
  return Boolean((form.value[field]?.[locale] ?? "").trim());
}

function localeIndicator(locale: LocaleKey): boolean {
  const fields: TranslatableKey[] = [
    "title",
    "subtitle",
    "description",
    "theme",
    "location",
    "presented_by",
    "moderator",
  ];
  return fields.some((f) => localeHasContent(f, locale));
}

function handleSubmit() {
  const payload: Record<string, unknown> = {
    date: form.value.date,
    start_time: formatTime(timeRange.value.start),
    end_time: formatTime(timeRange.value.end),
    title: cleanTranslatable(form.value.title),
    subtitle: cleanTranslatable(form.value.subtitle),
    description: cleanTranslatable(form.value.description),
    theme: cleanTranslatable(form.value.theme),
    location: cleanTranslatable(form.value.location),
    presented_by: cleanTranslatable(form.value.presented_by),
    moderator: cleanTranslatable(form.value.moderator),
    panelists: form.value.panelists.length ? form.value.panelists : null,
    speakers: form.value.speakers.length ? form.value.speakers : null,
    categories: form.value.categories,
    is_active: form.value.is_active,
  };

  const posterValue = imageFiles.value.poster?.[0];
  if (posterValue && posterValue.startsWith("tmp-")) {
    payload.tmp_poster = posterValue;
  } else if (deleteFlags.value.poster) {
    payload.poster_delete = true;
  }

  emit("submit", payload);
}

function cleanTranslatable(t: Translatable): Translatable {
  const out: Translatable = {};
  for (const [k, v] of Object.entries(t ?? {})) {
    out[k] = v && String(v).trim().length > 0 ? v : null;
  }
  return out;
}

function err(key: string): string | null {
  const e = props.errors?.[key];
  return e?.[0] ?? null;
}

function localizedError(field: TranslatableKey): string | null {
  return err(`${field}.${activeLocale.value}`) ?? err(field);
}
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-5">
    <!-- Global locale switcher -->
    <Tabs v-model="activeLocale" class="w-full">
      <TabsList class="bg-muted/60 inline-flex h-9 items-center gap-1 rounded-full p-1">
        <TabsTrigger
          v-for="locale in LOCALES"
          :key="locale.value"
          :value="locale.value"
          class="relative"
        >
          <span class="flex items-center gap-1.5">
            <span>{{ locale.label }}</span>
            <span
              v-if="localeIndicator(locale.value)"
              class="bg-primary inline-block size-1.5 rounded-full"
            />
          </span>
        </TabsTrigger>
      </TabsList>
    </Tabs>

    <!-- Poster image -->
    <div class="space-y-2">
      <Label class="text-sm">Poster Image</Label>
      <InputFileImage
        v-model="imageFiles.poster"
        v-model:delete-flag="deleteFlags.poster"
        :initial-image="initialPoster"
        container-class="relative isolate aspect-[16/9] max-w-md"
      />
      <p v-if="err('tmp_poster')" class="text-destructive text-xs">{{ err("tmp_poster") }}</p>
    </div>

    <!-- Date + Time -->
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="space-y-2">
        <Label class="text-sm">Date</Label>
        <DatePicker
          v-model="dateValue"
          :min="eventStart"
          :max="eventEnd"
          :placeholder-date="eventStart"
          placeholder="Pick a date"
        />
        <p v-if="err('date')" class="text-destructive text-xs">{{ err("date") }}</p>
      </div>
      <div class="space-y-2">
        <Label class="text-sm">Time</Label>
        <TimeRangePicker
          v-model="timeRange"
          clearable
          class="bg-background border-border rounded-md border px-3"
        />
        <p v-if="err('start_time') || err('end_time')" class="text-destructive text-xs">
          {{ err("start_time") || err("end_time") }}
        </p>
      </div>
    </div>

    <!-- Title (translatable, required) -->
    <div class="space-y-2">
      <Label class="text-sm">Title <span class="text-destructive">*</span></Label>
      <Input
        v-model="titleField"
        :placeholder="activeLocale === 'en' ? 'Session title' : 'Judul sesi'"
      />
      <p v-if="localizedError('title')" class="text-destructive text-xs">
        {{ localizedError("title") }}
      </p>
    </div>

    <!-- Subtitle -->
    <div class="space-y-2">
      <Label class="text-sm">Subtitle</Label>
      <Input
        v-model="subtitleField"
        :placeholder="activeLocale === 'en' ? 'Optional subtitle' : 'Subjudul opsional'"
      />
    </div>

    <!-- Theme + Location -->
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="space-y-2">
        <Label class="text-sm">Theme</Label>
        <Input
          v-model="themeField"
          :placeholder="activeLocale === 'en' ? 'Session theme' : 'Tema sesi'"
        />
      </div>
      <div class="space-y-2">
        <Label class="text-sm">Location</Label>
        <Input
          v-model="locationField"
          :placeholder="activeLocale === 'en' ? 'Venue or room' : 'Lokasi atau ruangan'"
        />
      </div>
    </div>

    <!-- Description -->
    <div class="space-y-2">
      <Label class="text-sm">Description</Label>
      <TipTapEditor
        v-model="descriptionField"
        placeholder="Optional details"
        :sticky="false"
        min-height="120px"
      />
    </div>

    <!-- Presented by + Moderator -->
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="space-y-2">
        <Label class="text-sm">Presented By</Label>
        <Input
          v-model="presentedByField"
          :placeholder="activeLocale === 'en' ? 'Sponsor or presenter' : 'Sponsor atau pembawa'"
        />
      </div>
      <div class="space-y-2">
        <Label class="text-sm">Moderator</Label>
        <Input
          v-model="moderatorField"
          :placeholder="activeLocale === 'en' ? 'Moderator name' : 'Nama moderator'"
        />
      </div>
    </div>

    <!-- Speakers -->
    <div class="space-y-2">
      <Label class="text-sm">Speakers</Label>
      <SpeakerListInput v-model="form.speakers" />
    </div>

    <!-- Panelists -->
    <div class="space-y-2">
      <Label class="text-sm">Panelists</Label>
      <PanelistListInput v-model="form.panelists" />
    </div>

    <!-- Categories tags -->
    <div class="space-y-2">
      <Label class="text-sm">Categories</Label>
      <TagsInput v-model="form.categories">
        <TagsInputItem v-for="tag in form.categories" :key="tag" :value="tag">
          <TagsInputItemText />
          <TagsInputItemDelete />
        </TagsInputItem>
        <TagsInputInput placeholder="Add category, press Enter" />
      </TagsInput>
    </div>

    <!-- Is active -->
    <div class="flex items-center gap-2">
      <Switch id="rundown-active" v-model="form.is_active" />
      <Label for="rundown-active" class="cursor-pointer">Visible to public</Label>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-2 pt-2">
      <button
        type="button"
        class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
        @click="emit('cancel')"
      >
        Cancel
      </button>
      <button
        type="submit"
        :disabled="loading"
        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <Spinner v-if="loading" class="size-4" />
        <span>{{ item ? "Save changes" : "Create item" }}</span>
      </button>
    </div>
  </form>
</template>

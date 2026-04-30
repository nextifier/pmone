<script setup lang="ts">
import InputFileImage from "@/components/InputFileImage.vue";
import PanelistListInput from "@/components/PanelistListInput.vue";
import SpeakerListInput from "@/components/SpeakerListInput.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import { Time } from "@internationalized/date";

type Translatable = Record<string, string | null>;
type Speaker = { name: string; title?: string; organization?: string };
type Panelist = { name: string; title?: string };
type LocalizedList<T> = T[] | Record<string, T[]>;
type RundownSettings = { is_group_header?: boolean | null } & Record<string, unknown>;

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
  panelists?: LocalizedList<Panelist> | null;
  speakers?: LocalizedList<Speaker> | null;
  categories?: string[];
  poster_image?: { sm?: string; md?: string; lg?: string; original?: string } | null;
  is_active?: boolean;
  settings?: RundownSettings | null;
};

function flattenLocalizedList<T>(
  value: LocalizedList<T> | null | undefined,
  preferred: string = "en"
): T[] {
  if (!value) return [];
  if (Array.isArray(value)) return value;
  if (typeof value === "object") {
    const dict = value as Record<string, T[]>;
    return (
      dict[preferred] ??
      dict.en ??
      dict.id ??
      Object.values(dict).find((v): v is T[] => Array.isArray(v)) ??
      []
    );
  }
  return [];
}

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
  panelists: flattenLocalizedList<Panelist>(props.item?.panelists),
  speakers: flattenLocalizedList<Speaker>(props.item?.speakers),
  categories: (props.item?.categories ?? []) as string[],
  is_active: props.item?.is_active ?? true,
  is_group_header: Boolean(props.item?.settings?.is_group_header),
});

const isGroupHeader = computed(() => form.value.is_group_header);

function toggleGroupHeader(checked: boolean | "indeterminate"): void {
  const next = checked === true;
  form.value.is_group_header = next;
  if (next) {
    form.value.date = null;
    form.value.location = { en: "", id: "" };
    form.value.description = { en: "", id: "" };
    form.value.presented_by = { en: "", id: "" };
    form.value.moderator = { en: "", id: "" };
    form.value.panelists = [];
    form.value.speakers = [];
    form.value.categories = [];
    timeRange.value = { start: undefined, end: undefined };
    imageFiles.value.poster = [];
    deleteFlags.value.poster = Boolean(props.item?.poster_image);
  }
}

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

function handleSubmit() {
  const existingSettings: RundownSettings = { ...(props.item?.settings ?? {}) };
  existingSettings.is_group_header = form.value.is_group_header;

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
    settings: existingSettings,
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

const { metaSymbol } = useShortcuts();

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      handleSubmit();
    },
  },
});
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-5">
    <!-- Global locale switcher -->
    <Tabs v-model="activeLocale" variant="segmented">
      <TabsList>
        <TabsIndicator />
        <TabsTrigger v-for="locale in LOCALES" :key="locale.value" :value="locale.value">
          {{ locale.label }}
        </TabsTrigger>
      </TabsList>
    </Tabs>

    <!-- Group header toggle -->
    <div class="bg-muted/40 border-border flex items-start gap-2 rounded-lg border p-3">
      <Checkbox
        id="rundown-is-group-header"
        :model-value="form.is_group_header"
        @update:model-value="toggleGroupHeader"
        class="mt-[3px]"
      />
      <Label
        for="rundown-is-group-header"
        class="flex grow cursor-pointer flex-col items-start gap-y-0.5 tracking-tight"
      >
        <span class="text-base font-medium">Use as session header</span>
        <span class="text-muted-foreground text-sm"
          >For dividers like Session I, Session II, or Opening, or Closing.</span
        >
      </Label>
    </div>

    <!-- Poster image -->
    <div v-if="!isGroupHeader" class="space-y-2">
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
      <div v-if="!isGroupHeader" class="space-y-2">
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

    <!-- Theme (always visible) -->
    <div class="space-y-2">
      <Label class="text-sm">Theme</Label>
      <Input
        v-model="themeField"
        :placeholder="activeLocale === 'en' ? 'Session theme' : 'Tema sesi'"
      />
    </div>

    <!-- Location (hidden for group headers) -->
    <div v-if="!isGroupHeader" class="space-y-2">
      <Label class="text-sm">Location</Label>
      <Input
        v-model="locationField"
        :placeholder="activeLocale === 'en' ? 'Venue or room' : 'Lokasi atau ruangan'"
      />
    </div>

    <!-- Description -->
    <div v-if="!isGroupHeader" class="space-y-2">
      <Label class="text-sm">Description</Label>
      <TipTapEditor
        v-model="descriptionField"
        placeholder="Optional details"
        :sticky="false"
        min-height="120px"
      />
    </div>

    <!-- Presented by + Moderator -->
    <div v-if="!isGroupHeader" class="grid gap-4 sm:grid-cols-2">
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
    <div v-if="!isGroupHeader" class="space-y-2">
      <Label class="text-sm">Speakers</Label>
      <SpeakerListInput v-model="form.speakers" />
    </div>

    <!-- Panelists -->
    <div v-if="!isGroupHeader" class="space-y-2">
      <Label class="text-sm">Panelists</Label>
      <PanelistListInput v-model="form.panelists" />
    </div>

    <!-- Categories tags -->
    <div v-if="!isGroupHeader" class="space-y-2">
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
      <Button type="button" variant="outline" @click="emit('cancel')"> Cancel </Button>
      <Button type="submit" :disabled="loading">
        <Spinner v-if="loading" class="size-4" />
        Save
        <KbdGroup>
          <Kbd>{{ metaSymbol }}</Kbd>
          <Kbd>S</Kbd>
        </KbdGroup>
      </Button>
    </div>
  </form>
</template>

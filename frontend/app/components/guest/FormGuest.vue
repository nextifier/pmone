<script setup lang="ts">
import InputFileImage from "@/components/InputFileImage.vue";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { RangeCalendarPicker } from "@/components/ui/range-calendar-picker";
import { Switch } from "@/components/ui/switch";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";

type Link = { id?: number; label: string; url: string; isCustomLabel?: boolean };
type ImageUrls = Record<string, string> & {
  sm?: string;
  md?: string;
  lg?: string;
  original?: string;
};

type AppearanceDate = { start?: string | null; end?: string | null };

type GuestPayload = {
  id?: number;
  name?: string;
  title?: string | null;
  bio?: string | null;
  organization?: string | null;
  status?: "active" | "inactive";
  visibility?: "public" | "private";
  is_featured?: boolean;
  appearance_date?: AppearanceDate | null;
  transparent_background?: boolean;
  tags?: string[];
  links?: Link[];
  more_details?: Record<string, unknown>;
  settings?: Record<string, unknown>;
  profile_image?: ImageUrls | null;
};

const props = withDefaults(
  defineProps<{
    guest?: GuestPayload | null;
    event?: { start_date?: string | null; end_date?: string | null } | null;
    loading?: boolean;
    errors?: Record<string, string[]>;
  }>(),
  {
    guest: null,
    event: null,
    loading: false,
    errors: () => ({}),
  }
);

function parseISODate(value?: string | null): Date | null {
  if (!value) return null;
  const [y, m, d] = value.slice(0, 10).split("-").map(Number);
  if (!y || !m || !d) return null;
  return new Date(y, m - 1, d);
}

function toISODate(date?: Date | null): string | null {
  if (!date) return null;
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

const eventStart = computed(() =>
  props.event?.start_date ? new Date(props.event.start_date) : null
);
const eventEnd = computed(() =>
  props.event?.end_date ? new Date(props.event.end_date) : null
);

const emit = defineEmits<{
  submit: [payload: Record<string, unknown>];
  cancel: [];
}>();

const PREDEFINED_LINK_LABELS = ["Website", "Instagram", "Facebook", "X", "TikTok", "LinkedIn", "YouTube"];

const form = ref({
  name: props.guest?.name ?? "",
  organization: props.guest?.organization ?? "",
  title: props.guest?.title ?? "",
  bio: props.guest?.bio ?? "",
  tags: [...(props.guest?.tags ?? [])] as string[],
  status: (props.guest?.status ?? "active") as "active" | "inactive",
  visibility: (props.guest?.visibility ?? "public") as "public" | "private",
  is_featured: props.guest?.is_featured ?? false,
  transparent_background: props.guest?.transparent_background ?? false,
  appearanceDate: {
    start: parseISODate(props.guest?.appearance_date?.start),
    end: parseISODate(props.guest?.appearance_date?.end),
  } as { start: Date | null; end: Date | null },
  links: (props.guest?.links ?? []).map((l) => ({
    label: l.label || "",
    url: l.url || "",
    isCustomLabel: !PREDEFINED_LINK_LABELS.includes(l.label),
  })) as Link[],
});

const imageFiles = ref<{ profile: string[] }>({ profile: [] });
const deleteFlags = ref<{ profile: boolean }>({ profile: false });

const initialProfile = computed(() => props.guest?.profile_image ?? null);

function addLink() {
  form.value.links.push({ label: "", url: "", isCustomLabel: false });
}

function removeLink(index: number) {
  form.value.links.splice(index, 1);
}

function handleLinkLabelChange(index: number, value: string) {
  if (value === "Custom") {
    form.value.links[index].isCustomLabel = true;
    form.value.links[index].label = "";
  } else {
    form.value.links[index].isCustomLabel = false;
    form.value.links[index].label = value;
  }
}

function handleSubmit() {
  const payload: Record<string, unknown> = {
    name: form.value.name,
    organization: form.value.organization || null,
    title: form.value.title?.trim() ? form.value.title.trim() : null,
    bio: form.value.bio?.trim() ? form.value.bio : null,
    tags: form.value.tags,
    status: form.value.status,
    visibility: form.value.visibility,
    is_featured: form.value.is_featured,
    transparent_background: form.value.transparent_background,
    appearance_date: form.value.appearanceDate.start
      ? {
          start: toISODate(form.value.appearanceDate.start),
          end: toISODate(form.value.appearanceDate.end ?? form.value.appearanceDate.start),
        }
      : null,
    links: form.value.links
      .filter((l) => l.url && l.label)
      .map((l) => ({ label: l.label, url: l.url })),
  };

  const profileValue = imageFiles.value.profile?.[0];
  if (profileValue && profileValue.startsWith("tmp-")) {
    payload.tmp_profile_image = profileValue;
  } else if (deleteFlags.value.profile) {
    payload.delete_profile_image = true;
  }

  emit("submit", payload);
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
  <form @submit.prevent="handleSubmit" class="grid gap-y-6">
    <div class="space-y-2">
      <Label>Profile Image</Label>
      <InputFileImage
        v-model="imageFiles.profile"
        v-model:delete-flag="deleteFlags.profile"
        :initial-image="initialProfile"
        container-class="relative isolate aspect-[4/5] max-w-[180px]"
      />
      <p class="text-muted-foreground text-sm tracking-tight">
        Recommended ratio 4:5 (portrait), JPG/PNG/WEBP, max 20MB
      </p>
      <FieldError :errors="errors.tmp_profile_image" />
    </div>

    <div class="flex items-center justify-between gap-3">
      <div class="space-y-1">
        <Label for="guest-transparent" class="cursor-pointer">
          Transparent background image
        </Label>
        <p class="text-muted-foreground text-sm tracking-tight">
          Enable for cut-out PNG photos - adds a soft backdrop behind the image.
        </p>
      </div>
      <Switch id="guest-transparent" v-model="form.transparent_background" />
    </div>

    <div class="grid grid-cols-1 gap-x-3 gap-y-6 sm:grid-cols-2">
      <div class="space-y-2">
        <Label for="guest-name">Name</Label>
        <Input id="guest-name" v-model="form.name" auto-focus required />
        <FieldError :errors="errors.name" />
      </div>
      <div class="space-y-2">
        <Label for="guest-organization">Organization</Label>
        <Input id="guest-organization" v-model="form.organization" />
        <FieldError :errors="errors.organization" />
      </div>
    </div>

    <div class="space-y-2">
      <Label for="guest-title">Title / Position</Label>
      <Input
        id="guest-title"
        v-model="form.title"
        placeholder="e.g. CEO, Founder, Keynote Speaker"
      />
      <FieldError :errors="errors.title" />
    </div>

    <div class="space-y-2">
      <Label>Appearance Date</Label>
      <RangeCalendarPicker
        v-model="form.appearanceDate"
        :min="eventStart"
        :max="eventEnd"
        :number-of-months="1"
        size="default"
        placeholder="Select the day(s) this guest appears"
      />
      <p class="text-muted-foreground text-sm tracking-tight">
        Event day(s) this guest appears - shown as a date badge on the website.
      </p>
      <FieldError :errors="errors.appearance_date" />
    </div>

    <div class="space-y-2">
      <Label>Bio</Label>
      <TipTapEditor
        v-model="form.bio"
        model-type="App\Models\Guest"
        collection="bio_images"
        :sticky="false"
        min-height="180px"
        placeholder="Write a short bio..."
      />
      <FieldError :errors="errors.bio" />
    </div>

    <div class="space-y-2">
      <Label>Topics / Expertise</Label>
      <TagsInput v-model="form.tags">
        <TagsInputItem v-for="tag in form.tags" :key="tag" :value="tag">
          <TagsInputItemText />
          <TagsInputItemDelete />
        </TagsInputItem>
        <TagsInputInput placeholder="Add topic..." />
      </TagsInput>
      <FieldError :errors="errors.tags" />
    </div>

    <div class="space-y-2">
      <Label>Links</Label>
      <div class="grid grid-cols-1 gap-y-3">
        <div v-if="form.links.length" class="space-y-2">
          <div
            v-for="(link, index) in form.links"
            :key="index"
            class="flex items-center gap-1.5"
          >
            <div class="min-w-28 sm:min-w-36">
              <Select
                v-model="link.label"
                @update:model-value="(value) => handleLinkLabelChange(index, value as string)"
              >
                <div v-if="link.isCustomLabel" class="relative">
                  <Input
                    v-model="link.label"
                    type="text"
                    placeholder="Enter custom label"
                    class="pr-7"
                  />
                  <SelectTrigger
                    class="absolute top-0 right-0 flex size-8 items-center justify-center border-transparent bg-transparent !p-0 [&_svg]:!m-0"
                  />
                </div>
                <SelectTrigger v-else class="w-full">
                  <SelectValue placeholder="Select label" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="label in PREDEFINED_LINK_LABELS"
                    :key="label"
                    :value="label"
                  >
                    {{ label }}
                  </SelectItem>
                  <SelectItem value="Custom">Custom</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <InputLink v-model="link.url" :label="link.label" class="grow" />

            <button
              type="button"
              @click="removeLink(index)"
              class="text-destructive hover:text-destructive/80 flex size-9 items-center justify-center rounded-lg transition"
            >
              <Icon name="hugeicons:delete-01" class="size-4" />
            </button>
          </div>
        </div>

        <button
          type="button"
          @click="addLink"
          class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition"
        >
          <Icon name="hugeicons:add-01" class="size-4" />
          Add Link
        </button>
      </div>
      <FieldError :errors="errors.links" />
    </div>

    <div class="flex items-center justify-between gap-3">
      <div class="space-y-1">
        <Label for="guest-featured" class="cursor-pointer">Featured</Label>
        <p class="text-muted-foreground text-sm tracking-tight">
          Highlight as keynote / featured speaker
        </p>
      </div>
      <Switch id="guest-featured" v-model="form.is_featured" />
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div class="space-y-2">
        <Label for="guest-status">Status</Label>
        <Select v-model="form.status">
          <SelectTrigger id="guest-status" class="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
        <FieldError :errors="errors.status" />
      </div>
      <div class="space-y-2">
        <Label for="guest-visibility">Visibility</Label>
        <Select v-model="form.visibility">
          <SelectTrigger id="guest-visibility" class="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="public">Public</SelectItem>
            <SelectItem value="private">Private</SelectItem>
          </SelectContent>
        </Select>
        <FieldError :errors="errors.visibility" />
      </div>
    </div>

    <!-- Submit -->
    <div class="flex justify-end gap-2 pt-2">
      <Button type="button" variant="outline" size="sm" @click="emit('cancel')"> Cancel </Button>
      <Button type="submit" size="sm" :disabled="loading">
        <Icon v-if="loading" name="svg-spinners:ring-resize" class="size-4" />
        {{ loading ? "Saving..." : "Save" }}
        <KbdGroup v-if="!loading">
          <Kbd>{{ metaSymbol }}</Kbd>
          <Kbd>S</Kbd>
        </KbdGroup>
      </Button>
    </div>
  </form>
</template>

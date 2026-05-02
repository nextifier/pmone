<script setup lang="ts">
import InputFileImage from "@/components/InputFileImage.vue";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";

type Link = { id?: number; label: string; url: string };
type ImageUrls = Record<string, string> & { sm?: string; md?: string; lg?: string; original?: string };

type GuestPayload = {
  id?: number;
  name?: string;
  title?: string | null;
  bio?: string | null;
  organization?: string | null;
  status?: "active" | "inactive";
  visibility?: "public" | "private";
  is_featured?: boolean;
  tags?: string[];
  links?: Link[];
  more_details?: Record<string, unknown>;
  settings?: Record<string, unknown>;
  profile_image?: ImageUrls | null;
};

const props = withDefaults(
  defineProps<{
    guest?: GuestPayload | null;
    loading?: boolean;
    errors?: Record<string, string[]>;
  }>(),
  {
    guest: null,
    loading: false,
    errors: () => ({}),
  }
);

const emit = defineEmits<{
  submit: [payload: Record<string, unknown>];
  cancel: [];
}>();

const PREDEFINED_LABELS = ["Website", "LinkedIn", "X", "Instagram", "Facebook", "YouTube", "TikTok"];

const form = ref({
  name: props.guest?.name ?? "",
  organization: props.guest?.organization ?? "",
  title: props.guest?.title ?? "",
  bio: props.guest?.bio ?? "",
  tags: [...(props.guest?.tags ?? [])] as string[],
  status: (props.guest?.status ?? "active") as "active" | "inactive",
  visibility: (props.guest?.visibility ?? "public") as "public" | "private",
  is_featured: props.guest?.is_featured ?? false,
  links: (props.guest?.links ?? []).map((l) => ({ label: l.label, url: l.url })),
});

const imageFiles = ref<{ profile: string[] }>({ profile: [] });
const deleteFlags = ref<{ profile: boolean }>({ profile: false });

const initialProfile = computed(() => props.guest?.profile_image ?? null);

function addLink() {
  form.value.links.push({ label: "Website", url: "" });
}

function removeLink(index: number) {
  form.value.links.splice(index, 1);
}

function err(key: string): string | null {
  const e = props.errors?.[key];
  return e?.[0] ?? null;
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
    links: form.value.links.filter((l) => l.url && l.label),
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
  <form @submit.prevent="handleSubmit" class="space-y-5">
    <!-- Profile image -->
    <div class="space-y-2">
      <Label class="text-sm">{{ $t("guests.profileImage") }}</Label>
      <InputFileImage
        v-model="imageFiles.profile"
        v-model:delete-flag="deleteFlags.profile"
        :initial-image="initialProfile"
        container-class="relative isolate aspect-[4/5] max-w-[200px]"
      />
      <p class="text-muted-foreground text-xs tracking-tight">
        {{ $t("guests.profileImageHint") }}
      </p>
      <p v-if="err('tmp_profile_image')" class="text-destructive text-xs">
        {{ err("tmp_profile_image") }}
      </p>
    </div>

    <!-- Name + Organization -->
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="space-y-2">
        <Label class="text-sm" for="guest-name">{{ $t("guests.name") }} *</Label>
        <Input id="guest-name" v-model="form.name" auto-focus required />
        <p v-if="err('name')" class="text-destructive text-xs">{{ err("name") }}</p>
      </div>
      <div class="space-y-2">
        <Label class="text-sm" for="guest-organization">{{ $t("guests.organization") }}</Label>
        <Input id="guest-organization" v-model="form.organization" />
        <p v-if="err('organization')" class="text-destructive text-xs">{{ err("organization") }}</p>
      </div>
    </div>

    <!-- Title -->
    <div class="space-y-2">
      <Label class="text-sm" for="guest-title">{{ $t("guests.title2") }}</Label>
      <Input
        id="guest-title"
        v-model="form.title"
        placeholder="e.g. CEO, Founder, Keynote Speaker"
      />
      <p v-if="err('title')" class="text-destructive text-xs">{{ err("title") }}</p>
    </div>

    <!-- Bio -->
    <div class="space-y-2">
      <Label class="text-sm">{{ $t("guests.bio") }}</Label>
      <TipTapEditor
        v-model="form.bio"
        model-type="App\Models\Guest"
        collection="bio_images"
        :sticky="false"
        min-height="160px"
        :placeholder="$t('guests.writeBio')"
      />
      <p v-if="err('bio')" class="text-destructive text-xs">{{ err("bio") }}</p>
    </div>

    <!-- Tags / Topics -->
    <div class="space-y-2">
      <Label class="text-sm">{{ $t("guests.topics") }}</Label>
      <TagsInput v-model="form.tags">
        <TagsInputItem v-for="tag in form.tags" :key="tag" :value="tag">
          <TagsInputItemText />
          <TagsInputItemDelete />
        </TagsInputItem>
        <TagsInputInput :placeholder="$t('guests.addTopic')" />
      </TagsInput>
    </div>

    <!-- Links -->
    <div class="space-y-2">
      <Label class="text-sm">{{ $t("guests.links") }}</Label>
      <div v-if="form.links.length" class="space-y-2">
        <div
          v-for="(link, index) in form.links"
          :key="index"
          class="flex items-center gap-1.5"
        >
          <Select v-model="link.label">
            <SelectTrigger class="w-32 shrink-0">
              <SelectValue placeholder="Label" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="label in PREDEFINED_LABELS" :key="label" :value="label">
                {{ label }}
              </SelectItem>
              <SelectItem value="Custom">Custom</SelectItem>
            </SelectContent>
          </Select>
          <Input v-model="link.url" type="url" placeholder="https://..." class="grow" />
          <button
            type="button"
            @click="removeLink(index)"
            class="text-muted-foreground hover:bg-muted hover:text-destructive inline-flex size-9 shrink-0 items-center justify-center rounded-md"
          >
            <Icon name="hugeicons:delete-01" class="size-4" />
          </button>
        </div>
      </div>
      <button
        type="button"
        @click="addLink"
        class="text-primary hover:text-primary/80 inline-flex items-center gap-1.5 text-sm font-medium tracking-tight"
      >
        <Icon name="hugeicons:add-01" class="size-4" />
        {{ $t("guests.addLink") }}
      </button>
    </div>

    <!-- Settings -->
    <div class="border-border space-y-3 rounded-lg border p-4">
      <div class="flex items-center justify-between gap-3">
        <div class="space-y-0.5">
          <Label for="guest-featured" class="cursor-pointer text-sm font-medium">
            {{ $t("guests.isFeatured") }}
          </Label>
          <p class="text-muted-foreground text-xs tracking-tight">
            {{ $t("guests.featuredHint") }}
          </p>
        </div>
        <Switch id="guest-featured" v-model="form.is_featured" />
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div class="space-y-2">
          <Label class="text-sm">{{ $t("guests.status") }}</Label>
          <Select v-model="form.status">
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="active">{{ $t("guests.active") }}</SelectItem>
              <SelectItem value="inactive">{{ $t("guests.inactive") }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="space-y-2">
          <Label class="text-sm">{{ $t("guests.visibility") }}</Label>
          <Select v-model="form.visibility">
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="public">{{ $t("guests.public") }}</SelectItem>
              <SelectItem value="private">{{ $t("guests.private") }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
    </div>

    <!-- Submit -->
    <div class="flex justify-end gap-2">
      <button
        type="button"
        @click="emit('cancel')"
        class="border-border hover:bg-muted rounded-md border px-3 py-1.5 text-sm tracking-tight"
      >
        {{ $t("guests.cancel") }}
      </button>
      <button
        type="submit"
        :disabled="loading"
        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight disabled:opacity-60"
      >
        <Icon v-if="loading" name="svg-spinners:ring-resize" class="size-4" />
        {{ loading ? $t("guests.saving") : $t("guests.save") }}
        <KbdGroup v-if="!loading">
          <Kbd>{{ metaSymbol }}</Kbd>
          <Kbd>S</Kbd>
        </KbdGroup>
      </button>
    </div>
  </form>
</template>

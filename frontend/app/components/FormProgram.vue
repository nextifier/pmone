<script setup>
import InputFileImage from "@/components/InputFileImage.vue";
import { IconPicker } from "@/components/ui/icon-picker";
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";

const props = defineProps({
  item: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["submit", "cancel"]);

const LOCALES = [
  { value: "en", label: "English" },
  { value: "id", label: "Indonesian" },
  { value: "ja", label: "日本語" },
  { value: "ko", label: "한국어" },
  { value: "zh", label: "中文" },
];

const EMPTY = () => ({ en: "", id: "", ja: "", ko: "", zh: "" });

const activeLocale = ref("en");

const form = ref({
  title: { ...EMPTY(), ...(props.item?.title ?? {}) },
  description: { ...EMPTY(), ...(props.item?.description ?? {}) },
  icon: props.item?.icon ?? null,
  is_active: props.item?.is_active ?? true,
});

const imageFiles = ref({ image: [] });
const deleteFlags = ref({ image: false });
const initialImage = computed(() => props.item?.image ?? null);

function bind(key) {
  return computed({
    get: () => form.value[key]?.[activeLocale.value] ?? "",
    set: (value) => {
      form.value[key] = { ...form.value[key], [activeLocale.value]: value };
    },
  });
}

const titleField = bind("title");
const descriptionField = bind("description");

function cleanTranslatable(t) {
  const out = {};
  for (const [k, v] of Object.entries(t ?? {})) {
    out[k] = v && String(v).trim().length > 0 ? v : null;
  }
  return out;
}

function err(key) {
  return props.errors?.[key]?.[0] ?? null;
}

function localizedError(field) {
  return err(`${field}.${activeLocale.value}`) ?? err(field);
}

function handleSubmit() {
  const payload = {
    title: cleanTranslatable(form.value.title),
    description: cleanTranslatable(form.value.description),
    icon: form.value.icon || null,
    is_active: form.value.is_active,
  };

  const imageValue = imageFiles.value.image?.[0];
  if (imageValue && imageValue.startsWith("tmp-")) {
    payload.tmp_image = imageValue;
  } else if (deleteFlags.value.image) {
    payload.delete_image = true;
  }

  emit("submit", payload);
}

const { metaSymbol } = useShortcuts();

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => handleSubmit(),
  },
});
</script>

<template>
  <form @submit.prevent="handleSubmit" class="space-y-5">
    <!-- Locale switcher -->
    <Tabs v-model="activeLocale" variant="segmented">
      <TabsList>
        <TabsIndicator />
        <TabsTrigger v-for="locale in LOCALES" :key="locale.value" :value="locale.value">
          {{ locale.label }}
        </TabsTrigger>
      </TabsList>
    </Tabs>

    <!-- Title (translatable, required) -->
    <div class="space-y-2">
      <Label class="text-sm">Title</Label>
      <Input
        v-model="titleField"
        :placeholder="activeLocale === 'en' ? 'Program title' : 'Judul program'"
      />
      <p v-if="localizedError('title')" class="text-destructive text-xs">
        {{ localizedError("title") }}
      </p>
    </div>

    <!-- Description (translatable) -->
    <div class="space-y-2">
      <Label class="text-sm">Description</Label>
      <Textarea
        v-model="descriptionField"
        :rows="4"
        :placeholder="activeLocale === 'en' ? 'Short description' : 'Deskripsi singkat'"
      />
      <p v-if="localizedError('description')" class="text-destructive text-xs">
        {{ localizedError("description") }}
      </p>
    </div>

    <!-- Icon + Image (variant is inferred from whichever is filled) -->
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="space-y-2">
        <Label class="text-sm">Icon</Label>
        <IconPicker v-model="form.icon" prefix="hugeicons" placeholder="Pick an icon" />
        <p class="text-muted-foreground text-xs tracking-tight">For icon-style cards.</p>
      </div>

      <div class="space-y-2">
        <Label class="text-sm">Image</Label>
        <InputFileImage
          v-model="imageFiles.image"
          v-model:delete-flag="deleteFlags.image"
          :initial-image="initialImage"
          container-class="relative isolate aspect-[2/3] max-w-[10rem]"
        />
        <p class="text-muted-foreground text-xs tracking-tight">For image-style cards.</p>
        <p v-if="err('tmp_image')" class="text-destructive text-xs">{{ err("tmp_image") }}</p>
      </div>
    </div>

    <!-- Is active -->
    <div class="flex items-center gap-2">
      <Switch id="program-active" v-model="form.is_active" />
      <Label for="program-active" class="cursor-pointer">Visible to public</Label>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-2 pt-2">
      <Button type="button" variant="outline" @click="emit('cancel')">Cancel</Button>
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

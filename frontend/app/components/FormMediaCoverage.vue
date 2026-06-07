<script setup>
import { DatePicker } from "@/components/ui/date-picker";

const props = defineProps({
  item: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["submit", "cancel"]);

const form = ref({
  title: props.item?.title ?? "",
  url: props.item?.url ?? "",
  published_at: props.item?.published_at ? new Date(props.item.published_at) : null,
  is_active: props.item?.is_active ?? true,
});

function err(key) {
  return props.errors?.[key]?.[0] ?? null;
}

function toBackendDate(date) {
  if (!date) return null;
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function handleSubmit() {
  emit("submit", {
    title: form.value.title?.trim() ?? "",
    url: form.value.url?.trim() ?? "",
    published_at: toBackendDate(form.value.published_at),
    is_active: form.value.is_active,
  });
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
    <!-- Title -->
    <div class="space-y-2">
      <Label class="text-sm">Title</Label>
      <Input v-model="form.title" placeholder="Article headline" auto-focus />
      <p v-if="err('title')" class="text-destructive text-xs">{{ err("title") }}</p>
    </div>

    <!-- Article URL -->
    <div class="space-y-2">
      <Label class="text-sm">Article URL</Label>
      <Input v-model="form.url" type="url" placeholder="https://example.com/article" />
      <p class="text-muted-foreground text-xs tracking-tight">
        The publication's domain is shown automatically on the website.
      </p>
      <p v-if="err('url')" class="text-destructive text-xs">{{ err("url") }}</p>
    </div>

    <!-- Published date -->
    <div class="space-y-2">
      <Label class="text-sm">Published Date</Label>
      <DatePicker v-model="form.published_at" placeholder="Pick the publish date" />
      <p v-if="err('published_at')" class="text-destructive text-xs">
        {{ err("published_at") }}
      </p>
    </div>

    <!-- Is active -->
    <div class="flex items-center gap-2">
      <Switch id="media-coverage-active" v-model="form.is_active" />
      <Label for="media-coverage-active" class="cursor-pointer">Visible to public</Label>
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

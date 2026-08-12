<template>
  <div class="space-y-3">
    <div class="space-y-1">
      <h2 class="text-base font-semibold tracking-tighter">Start from</h2>
      <p class="text-muted-foreground text-sm tracking-tight">
        A template fills in the title, the description and a set of fields you can edit
        afterwards.
      </p>
    </div>

    <div v-if="pending" class="grid gap-2 sm:grid-cols-2">
      <Skeleton v-for="i in 4" :key="i" class="h-20 w-full rounded-xl" />
    </div>

    <div v-else class="grid gap-2 sm:grid-cols-2">
      <!-- Blank is a real option, not the absence of one, so it gets a card of
           its own and starts selected. -->
      <button
        type="button"
        :aria-pressed="!modelValue"
        class="flex flex-col items-start gap-y-1 rounded-xl border p-3 text-left transition-colors"
        :class="!modelValue ? 'border-primary bg-primary/10' : 'border-border hover:bg-muted/60'"
        @click="$emit('update:modelValue', null)"
      >
        <span class="flex items-center gap-x-1.5 text-sm font-medium tracking-tight">
          <Icon name="hugeicons:file-01" class="text-muted-foreground size-4 shrink-0" />
          Blank form
        </span>
        <span class="text-muted-foreground text-xs tracking-tight">
          Start with nothing and add your own fields.
        </span>
      </button>

      <button
        v-for="template in templates"
        :key="template.key"
        type="button"
        :aria-pressed="modelValue === template.key"
        class="flex flex-col items-start gap-y-1 rounded-xl border p-3 text-left transition-colors"
        :class="
          modelValue === template.key
            ? 'border-primary bg-primary/10'
            : 'border-border hover:bg-muted/60'
        "
        @click="$emit('update:modelValue', template.key)"
      >
        <span class="flex w-full items-center gap-x-2 text-sm font-medium tracking-tight">
          <Icon name="hugeicons:task-daily-01" class="text-muted-foreground size-4 shrink-0" />
          <span class="truncate">{{ template.title }}</span>
          <span class="text-muted-foreground ml-auto shrink-0 text-xs tabular-nums">
            {{ template.field_count }}
          </span>
        </span>
        <span v-if="template.description" class="text-muted-foreground line-clamp-2 text-xs tracking-tight">
          {{ stripHtml(template.description) }}
        </span>
      </button>
    </div>
  </div>
</template>

<script setup>
/**
 * `FormTemplates` and `GET /api/form-templates` have been in the backend since
 * the feature shipped, and `FormController@store` already seeds fields from a
 * `template` key - but nothing in the UI ever asked for one.
 */
import { Skeleton } from "@/components/ui/skeleton";

defineProps({
  /** Selected template key, or null for a blank form. */
  modelValue: { type: String, default: null },
});

const emit = defineEmits(["update:modelValue", "loaded"]);

const client = useSanctumClient();
const templates = ref([]);
const pending = ref(true);

/** Descriptions are stored as HTML for the public page; the card wants text. */
const stripHtml = (value) =>
  String(value ?? "")
    .replace(/<[^>]*>/g, " ")
    .replace(/\s+/g, " ")
    .trim();

onMounted(async () => {
  try {
    const response = await client("/api/form-templates");
    templates.value = response?.data ?? [];
    emit("loaded", templates.value);
  } catch {
    // A picker that cannot load is not worth an error toast on a create page:
    // the blank card still works and that is the common path anyway.
    templates.value = [];
  } finally {
    pending.value = false;
  }
});
</script>

<script setup>
import { Tabs, TabsIndicator, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import { useClipboard } from "@vueuse/core";
import { toast } from "vue-sonner";

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

// Mirrors App\Support\FaqTemplate::tokens() — resolved server-side from the event/project context.
const VARIABLES = [
  "{{event_title}}",
  "{{event_date}}",
  "{{event_time}}",
  "{{event_location}}",
  "{{event_hall}}",
  "{{location_link}}",
  "{{contact_email}}",
  "{{whatsapp_link}}",
  "{{instagram}}",
];

const EMPTY = () => ({ en: "", id: "", ja: "", ko: "", zh: "" });

const activeLocale = ref("en");

const form = ref({
  question: { ...EMPTY(), ...(props.item?.question ?? {}) },
  answer: { ...EMPTY(), ...(props.item?.answer ?? {}) },
  is_active: props.item?.is_active ?? true,
});

function bind(key) {
  return computed({
    get: () => form.value[key]?.[activeLocale.value] ?? "",
    set: (value) => {
      form.value[key] = { ...form.value[key], [activeLocale.value]: value };
    },
  });
}

const questionField = bind("question");
const answerField = bind("answer");

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
  emit("submit", {
    question: cleanTranslatable(form.value.question),
    answer: cleanTranslatable(form.value.answer),
    is_active: form.value.is_active,
  });
}

const { copy } = useClipboard();

function copyVariable(token) {
  copy(token);
  toast.success(`Copied ${token}`);
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

    <!-- Question -->
    <div class="space-y-2">
      <Label class="text-sm">Question</Label>
      <Input
        v-model="questionField"
        :placeholder="activeLocale === 'en' ? 'Question' : 'Pertanyaan'"
      />
      <p v-if="localizedError('question')" class="text-destructive text-xs">
        {{ localizedError("question") }}
      </p>
    </div>

    <!-- Answer -->
    <div class="space-y-2">
      <Label class="text-sm">Answer</Label>
      <TipTapEditor
        v-model="answerField"
        placeholder="Answer (rich text)"
        :sticky="false"
        min-height="140px"
      />
      <p v-if="localizedError('answer')" class="text-destructive text-xs">
        {{ localizedError("answer") }}
      </p>
    </div>

    <!-- Variable legend -->
    <div class="bg-muted/40 border-border space-y-2 rounded-lg border p-3">
      <p class="text-muted-foreground text-sm tracking-tight">
        Available variables (auto-filled from the event &amp; project, click to copy):
      </p>
      <div class="flex flex-wrap gap-1.5">
        <button
          v-for="token in VARIABLES"
          :key="token"
          type="button"
          class="bg-background border-border hover:bg-muted rounded-md border px-2 py-1 font-mono text-xs tracking-tight active:scale-98"
          @click="copyVariable(token)"
        >
          {{ token }}
        </button>
      </div>
    </div>

    <!-- Is active -->
    <div class="flex items-center gap-2">
      <Switch id="faq-active" v-model="form.is_active" />
      <Label for="faq-active" class="cursor-pointer">Visible to public</Label>
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

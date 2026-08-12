<template>
  <Field :data-invalid="!!errors">
    <FieldLabel :for="`${idPrefix}_option_0`">Options</FieldLabel>

    <div class="space-y-2">
      <div v-for="(option, index) in modelValue" :key="index" class="flex items-center gap-x-2">
        <!-- Library fields own their options in the catalog, so here they are a
             record of what will be asked, not something to edit. -->
        <div
          v-if="readonly"
          class="text-muted-foreground bg-muted/40 flex-1 rounded-md border px-3 py-2 text-sm tracking-tight"
        >
          {{ displayText(option) }}
        </div>

        <template v-else>
          <Input
            :id="`${idPrefix}_option_${index}`"
            :model-value="labelText(option)"
            :placeholder="mode === 'pairs' ? 'Option label' : `Option ${index + 1}`"
            :aria-invalid="!!errors"
            :class="mode === 'pairs' ? 'flex-1' : undefined"
            @update:model-value="(v) => setLabel(index, v)"
          />
          <!-- Only the pairs mode exposes the stored value separately. The
               others derive it from the label, which is what their endpoints
               expect back. -->
          <Input
            v-if="mode === 'pairs'"
            :model-value="option.value"
            placeholder="Value"
            class="w-24 sm:w-28"
            :aria-label="`Value for option ${index + 1}`"
            @update:model-value="(v) => setValue(index, v)"
          />
          <Button
            type="button"
            variant="ghost"
            size="iconSm"
            class="hover:bg-destructive/10 text-destructive-foreground shrink-0"
            v-tippy="'Remove'"
            :aria-label="`Remove option ${index + 1}`"
            @click="removeOption(index)"
          >
            <Icon name="hugeicons:delete-02" class="size-4" />
          </Button>
        </template>
      </div>
    </div>

    <div v-if="!readonly" class="flex items-center gap-2">
      <Button type="button" variant="outline" size="sm" @click="addOption">
        <Icon name="hugeicons:add-01" class="size-3.5" />
        <span>Add option</span>
      </Button>
      <Button
        v-if="allowBulk"
        type="button"
        variant="ghost"
        size="sm"
        @click="showBulk = !showBulk"
      >
        <Icon name="hugeicons:list-plus" class="size-3.5" />
        <span>Bulk add</span>
      </Button>
    </div>
    <p v-else-if="readonlyNote" class="text-muted-foreground text-xs tracking-tight">
      {{ readonlyNote }}
    </p>

    <div v-if="showBulk && !readonly" class="space-y-2">
      <Textarea
        v-model="bulkText"
        :rows="4"
        :aria-label="'Bulk add options'"
        :placeholder="
          mode === 'pairs'
            ? 'One option per line. Use label|value to set a custom value.'
            : 'One option per line.'
        "
      />
      <Button type="button" size="sm" :disabled="!bulkText.trim()" @click="applyBulk">
        Add {{ bulkCount }} {{ bulkCount === 1 ? "option" : "options" }}
      </Button>
    </div>

    <FieldError :errors="errors" />
  </Field>
</template>

<script setup>
/**
 * The options list for choice-type fields.
 *
 * Three of the four authoring surfaces had a character-identical copy of this
 * block; the form builder had a fourth variant with a separate value input and
 * a bulk-add box. `mode` is the one real axis between them, because the shape
 * each endpoint stores genuinely differs:
 *
 * - `strings` - plain `string[]` (brand fields, ops documents)
 * - `single`  - `{value, label}` edited through one input (event contexts)
 * - `pairs`   - `{label, value}` with both exposed (form builder)
 */
import { Button } from "@/components/ui/button";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { localizedLabel } from "@/components/ui/custom-field";

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  mode: {
    type: String,
    default: "strings",
    validator: (value) => ["strings", "single", "pairs"].includes(value),
  },
  readonly: { type: Boolean, default: false },
  readonlyNote: { type: String, default: "" },
  allowBulk: { type: Boolean, default: false },
  errors: { type: [Array, String, null], default: null },
  /** Follows the host's language tab, so `single` mode shows the right label. */
  locale: { type: String, default: "en" },
  idPrefix: { type: String, default: "field" },
});

const emit = defineEmits(["update:modelValue"]);

const showBulk = ref(false);
const bulkText = ref("");

const isStrings = computed(() => props.mode === "strings");

const labelText = (option) => {
  if (isStrings.value) return String(option ?? "");
  if (props.mode === "single") {
    return localizedLabel(option.label, props.locale) || String(option.value ?? "");
  }
  return option.label ?? "";
};

/** Read-only rows never need the raw value, only what the field will show. */
const displayText = (option) => labelText(option) || String(option?.value ?? "");

const replace = (next) => emit("update:modelValue", next);

const setLabel = (index, text) => {
  const next = [...props.modelValue];
  if (isStrings.value) {
    next[index] = text;
  } else if (props.mode === "single") {
    // One input drives both halves: these endpoints store the typed text as the
    // value too, and splitting them here would strand the old value.
    next[index] = { value: text, label: text };
  } else {
    next[index] = { ...next[index], label: text };
  }
  replace(next);
};

const setValue = (index, text) => {
  const next = [...props.modelValue];
  next[index] = { ...next[index], value: text };
  replace(next);
};

const addOption = () => {
  replace([...props.modelValue, isStrings.value ? "" : { label: "", value: "" }]);
};

const removeOption = (index) => {
  replace(props.modelValue.filter((_, i) => i !== index));
};

const parseBulk = () =>
  bulkText.value
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      if (isStrings.value) return line;
      const [label, value] = line.split("|").map((part) => part.trim());
      return { label, value: value || label.toLowerCase().replace(/\s+/g, "-") };
    });

const bulkCount = computed(() => parseBulk().length);

const applyBulk = () => {
  const existing = isStrings.value
    ? props.modelValue.filter((o) => String(o).trim())
    : props.modelValue.filter((o) => o.label || o.value);

  replace([...existing, ...parseBulk()]);
  bulkText.value = "";
  showBulk.value = false;
};
</script>

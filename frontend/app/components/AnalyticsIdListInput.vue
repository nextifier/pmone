<script setup lang="ts">
import { computed } from "vue";

/**
 * Repeatable list of analytics id strings (GA4 / TikTok / Meta / GTM), for the
 * website settings Analytics section. Always renders at least one row so a
 * single-id project still feels like a plain text field - the extra rows only
 * appear once the operator adds them. The parent trims/dedupes and drops empty
 * rows before persisting (see website-settings.vue `cleanIds`).
 */
const props = withDefaults(
  defineProps<{
    modelValue: string[] | null | undefined;
    placeholder?: string;
    addLabel?: string;
    inputId?: string;
  }>(),
  {
    modelValue: () => [],
    placeholder: "",
    addLabel: "Add another ID",
    inputId: undefined,
  }
);

const emit = defineEmits<{
  "update:modelValue": [value: string[]];
}>();

const rows = computed<string[]>(() =>
  props.modelValue && props.modelValue.length ? props.modelValue : [""]
);

function update(next: string[]) {
  emit("update:modelValue", next);
}

function patch(index: number, value: string) {
  update(rows.value.map((v, i) => (i === index ? value : v)));
}

function add() {
  update([...rows.value, ""]);
}

function remove(index: number) {
  update(rows.value.filter((_, i) => i !== index));
}
</script>

<template>
  <div class="space-y-2">
    <div v-for="(value, index) in rows" :key="index" class="flex items-center gap-2">
      <Input
        :id="index === 0 ? inputId : undefined"
        :model-value="value"
        :placeholder="placeholder"
        class="flex-1"
        @update:model-value="(v) => patch(index, String(v))"
      />
      <button
        v-if="rows.length > 1"
        type="button"
        class="hover:bg-destructive/10 inline-flex size-9 shrink-0 items-center justify-center rounded-md"
        @click="remove(index)"
        v-tippy="'Remove ID'"
      >
        <Icon name="lucide:x" class="text-destructive size-3.5" />
      </button>
    </div>

    <button
      type="button"
      class="border-border hover:bg-muted text-muted-foreground flex w-full items-center justify-center gap-x-1.5 rounded-md border border-dashed py-1.5 text-xs tracking-tight active:scale-99 sm:text-sm"
      @click="add"
    >
      <Icon name="lucide:plus" class="size-3.5 shrink-0" />
      <span>{{ addLabel }}</span>
    </button>
  </div>
</template>

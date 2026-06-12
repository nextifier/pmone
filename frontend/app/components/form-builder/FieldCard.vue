<template>
  <div
    class="bg-muted/50 flex items-center gap-x-3 rounded-lg border px-3 py-2.5"
    :class="{ 'border-dashed': isSection }"
  >
    <Icon
      name="lucide:grip-vertical"
      class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab"
    />

    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
        <Icon :name="getTypeIcon(field.type)" class="text-muted-foreground size-4 shrink-0" />
        <span class="text-sm font-medium tracking-tight">{{ field.label }}</span>
        <span class="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-xs tracking-tight">
          {{ getTypeLabel(field.type) }}
        </span>
      </div>
      <div
        v-if="hasOptions(field.type) && field.options?.length"
        class="text-muted-foreground mt-0.5 truncate text-xs tracking-tight"
      >
        Options: {{ field.options.map((o) => o.label || o).join(", ") }}
      </div>
      <div v-if="field.help_text" class="text-muted-foreground mt-0.5 truncate text-xs tracking-tight">
        {{ field.help_text }}
      </div>
    </div>

    <div v-if="!isSection" class="flex shrink-0 items-center gap-x-1.5" @click.stop>
      <Switch
        :model-value="!!field.validation?.required"
        v-tippy="'Required'"
        class="scale-90"
        @update:model-value="$emit('toggle-required', field, $event)"
      />
    </div>

    <div class="flex shrink-0 items-center gap-x-0.5">
      <button
        type="button"
        v-tippy="'Duplicate'"
        aria-label="Duplicate field"
        @click="$emit('duplicate', field)"
        class="text-muted-foreground hover:text-foreground hover:bg-muted flex size-7 items-center justify-center rounded-md transition-colors"
      >
        <Icon name="lucide:copy" class="size-4" />
      </button>
      <button
        type="button"
        v-tippy="'Edit'"
        aria-label="Edit field"
        @click="$emit('edit', field)"
        class="text-muted-foreground hover:text-foreground hover:bg-muted flex size-7 items-center justify-center rounded-md transition-colors"
      >
        <Icon name="lucide:pencil" class="size-4" />
      </button>
      <button
        type="button"
        v-tippy="'Delete'"
        aria-label="Delete field"
        @click="$emit('delete', field)"
        class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 flex size-7 items-center justify-center rounded-md transition-colors"
      >
        <Icon name="lucide:trash-2" class="size-4" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { Switch } from "@/components/ui/switch";
import { getTypeIcon, getTypeLabel, hasOptions } from "@/lib/formFieldTypes";

const props = defineProps({
  field: { type: Object, required: true },
});

defineEmits(["edit", "delete", "duplicate", "toggle-required"]);

const isSection = computed(() => props.field.type === "section");
</script>

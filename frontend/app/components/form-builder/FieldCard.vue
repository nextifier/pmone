<template>
  <div class="bg-muted/50 flex items-center gap-x-3 rounded-lg border px-3 py-2.5">
    <Icon
      name="lucide:grip-vertical"
      class="drag-handle text-muted-foreground size-4 shrink-0 cursor-grab"
    />

    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-x-2">
        <Icon :name="fieldTypeIcon(field.type)" class="text-muted-foreground size-4 shrink-0" />
        <span class="text-sm font-medium">{{ field.label }}</span>
        <span class="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-xs">
          {{ fieldTypeLabel(field.type) }}
        </span>
        <span v-if="field.validation?.required" class="text-destructive text-xs">Required</span>
      </div>
      <div
        v-if="hasOptions(field.type) && field.options?.length"
        class="text-muted-foreground mt-0.5 text-xs"
      >
        Options: {{ field.options.map((o) => o.label || o).join(", ") }}
      </div>
    </div>

    <div class="flex items-center gap-x-1">
      <button
        type="button"
        @click="$emit('edit', field)"
        class="text-muted-foreground hover:text-foreground rounded p-1 transition"
      >
        <Icon name="lucide:pencil" class="size-3.5" />
      </button>
      <button
        type="button"
        @click="$emit('delete', field)"
        class="text-muted-foreground hover:text-destructive rounded p-1 transition"
      >
        <Icon name="lucide:trash-2" class="size-3.5" />
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  field: { type: Object, required: true },
});

defineEmits(["edit", "delete"]);

const fieldTypeIcon = (type) => {
  const icons = {
    text: "lucide:type",
    textarea: "lucide:align-left",
    email: "lucide:mail",
    number: "lucide:hash",
    phone: "lucide:phone",
    url: "lucide:link",
    date: "lucide:calendar",
    time: "lucide:clock",
    select: "lucide:chevrons-up-down",
    multi_select: "lucide:list-checks",
    checkbox: "lucide:square-check",
    checkbox_group: "lucide:list-checks",
    radio: "lucide:circle-dot",
    file: "lucide:paperclip",
    rating: "lucide:star",
    linear_scale: "lucide:sliders-horizontal",
  };
  return icons[type] || "lucide:type";
};

const fieldTypeLabel = (type) => {
  const labels = {
    text: "Text",
    textarea: "Textarea",
    email: "Email",
    number: "Number",
    phone: "Phone",
    url: "URL",
    date: "Date",
    time: "Time",
    select: "Select",
    multi_select: "Multi Select",
    checkbox: "Checkbox",
    checkbox_group: "Checkbox Group",
    radio: "Radio",
    file: "File Upload",
    rating: "Rating",
    linear_scale: "Linear Scale",
  };
  return labels[type] || type;
};

const hasOptions = (type) => {
  return ["select", "multi_select", "checkbox_group", "radio"].includes(type);
};
</script>

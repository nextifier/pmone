<template>
  <div class="space-y-4">
    <div v-for="group in fieldGroups" :key="group.label">
      <h4 class="text-muted-foreground mb-2 text-xs font-medium">{{ group.label }}</h4>
      <div class="grid grid-cols-3 gap-2">
        <button
          v-for="type in group.types"
          :key="type.value"
          type="button"
          @click="$emit('select', type.value)"
          class="flex flex-col items-center gap-y-1 rounded-lg border p-3 text-xs tracking-tight transition"
          :class="
            selected === type.value
              ? 'border-primary bg-primary/5 text-primary'
              : 'border-border hover:bg-muted'
          "
        >
          <Icon :name="type.icon" class="size-5" />
          <span>{{ type.label }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  selected: { type: String, default: null },
});

defineEmits(["select"]);

const fieldGroups = [
  {
    label: "Text",
    types: [
      { value: "text", label: "Text", icon: "lucide:type" },
      { value: "textarea", label: "Textarea", icon: "lucide:align-left" },
      { value: "email", label: "Email", icon: "lucide:mail" },
      { value: "number", label: "Number", icon: "lucide:hash" },
      { value: "phone", label: "Phone", icon: "lucide:phone" },
      { value: "url", label: "URL", icon: "lucide:link" },
    ],
  },
  {
    label: "Date / Time",
    types: [
      { value: "date", label: "Date", icon: "lucide:calendar" },
      { value: "time", label: "Time", icon: "lucide:clock" },
    ],
  },
  {
    label: "Selection",
    types: [
      { value: "select", label: "Select", icon: "lucide:chevrons-up-down" },
      { value: "multi_select", label: "Multi Select", icon: "lucide:list-checks" },
      { value: "checkbox", label: "Checkbox", icon: "lucide:square-check" },
      { value: "checkbox_group", label: "Checkboxes", icon: "lucide:list-checks" },
      { value: "radio", label: "Radio", icon: "lucide:circle-dot" },
    ],
  },
  {
    label: "Other",
    types: [
      { value: "file", label: "File", icon: "lucide:paperclip" },
      { value: "rating", label: "Rating", icon: "lucide:star" },
      { value: "linear_scale", label: "Scale", icon: "lucide:sliders-horizontal" },
    ],
  },
];
</script>

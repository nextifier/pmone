<template>
  <div class="space-y-5">
    <div v-for="group in groups" :key="group.key">
      <h4 class="text-muted-foreground mb-2 text-xs font-medium tracking-tight">
        {{ group.label }}
      </h4>
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        <button
          v-for="type in group.types"
          :key="type.value"
          type="button"
          @click="$emit('select', type.value)"
          class="flex flex-col gap-y-2 rounded-xl border p-2 text-center transition-colors"
          :class="
            selected === type.value
              ? 'border-primary bg-primary/5'
              : 'border-border hover:bg-muted/60'
          "
        >
          <div
            class="flex h-20 items-center justify-center overflow-hidden rounded-lg border p-3"
            :class="
              selected === type.value
                ? 'border-primary/30 bg-primary/5'
                : 'border-border/60 bg-muted/40'
            "
          >
            <FieldIllustration :type="type.value" />
          </div>
          <span
            class="text-xs font-medium tracking-tight sm:text-sm"
            :class="selected === type.value ? 'text-primary' : ''"
          >
            {{ type.label }}
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import FieldIllustration from "@/components/form-builder/FieldIllustration.vue";
import { FIELD_GROUPS, FIELD_TYPES } from "@/lib/formFieldTypes";

defineProps({
  selected: { type: String, default: null },
});

defineEmits(["select"]);

const groups = FIELD_GROUPS.map((group) => ({
  ...group,
  types: Object.entries(FIELD_TYPES)
    .filter(([, config]) => config.group === group.key)
    .map(([value, config]) => ({ value, label: config.label, icon: config.icon })),
})).filter((group) => group.types.length);
</script>

<template>
  <div class="flex items-center gap-2 text-sm">
    <!-- Saving -->
    <div v-if="isSaving" class="text-muted-foreground flex items-center gap-2">
      <Spinner class="size-4" />
      <span>Saving...</span>
    </div>

    <!-- Saved -->
    <div v-else-if="isSaved && lastSavedAt" class="text-muted-foreground flex items-center gap-2">
      <IconCheck class="size-4 text-green-600" />
      <span>Saved {{ formattedTime }}</span>
    </div>

    <!-- Error -->
    <div v-else-if="hasError" class="flex items-center gap-2 text-red-600">
      <Icon name="hugeicons:alert-circle" class="size-4 shrink-0" />
      <span>{{ error || "Failed to save" }}</span>
    </div>

    <!-- Idle -->
    <div v-else class="text-muted-foreground flex items-center gap-2">
      <span>Autosave enabled</span>
    </div>
  </div>
</template>

<script setup>
import { useTimeAgo } from "@vueuse/core";
import { computed } from "vue";

const props = defineProps({
  isSaving: {
    type: Boolean,
    default: false,
  },
  isSaved: {
    type: Boolean,
    default: false,
  },
  hasError: {
    type: Boolean,
    default: false,
  },
  lastSavedAt: {
    type: [Date, null],
    default: null,
  },
  error: {
    type: [String, null],
    default: null,
  },
});

// Compute the date to use for timeAgo - use a fallback date when null
const dateForTimeAgo = computed(() => props.lastSavedAt || new Date());
const timeAgo = useTimeAgo(dateForTimeAgo);

const formattedTime = computed(() => {
  if (!props.lastSavedAt) return "";
  return timeAgo.value;
});
</script>

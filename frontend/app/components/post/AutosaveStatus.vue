<template>
  <div class="flex items-center gap-2 text-sm">
    <!-- Saving -->
    <div v-if="isSaving" class="flex items-center gap-2 text-muted-foreground">
      <Spinner class="size-4" />
      <span>Saving...</span>
    </div>

    <!-- Saved -->
    <div v-else-if="isSaved && lastSavedAt" class="flex items-center gap-2 text-muted-foreground">
      <IconCheck class="size-4 text-green-600" />
      <span>Saved {{ formattedTime }}</span>
    </div>

    <!-- Error -->
    <div v-else-if="hasError" class="flex items-center gap-2 text-red-600">
      <IconAlertCircle class="size-4" />
      <span>{{ error || 'Failed to save' }}</span>
    </div>

    <!-- Idle -->
    <div v-else class="flex items-center gap-2 text-muted-foreground">
      <span>Autosave enabled</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTimeAgo } from '@vueuse/core'

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
})

const formattedTime = computed(() => {
  if (!props.lastSavedAt) return ''
  const timeAgo = useTimeAgo(props.lastSavedAt)
  return timeAgo.value
})
</script>

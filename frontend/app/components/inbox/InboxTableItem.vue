<template>
  <div class="flex flex-col gap-y-1 overflow-hidden">
    <div class="flex items-center gap-x-2">
      <NuxtLink
        :to="`/inbox/${submission.ulid}`"
        class="text-foreground font-medium text-sm tracking-tight hover:underline"
      >
        {{ submission.subject || "No Subject" }}
      </NuxtLink>
    </div>

    <div class="text-muted-foreground flex flex-wrap items-center gap-x-2 text-xs tracking-tight">
      <span v-if="submission.form_data_preview?.name">
        {{ submission.form_data_preview.name }}
      </span>
      <span v-if="submission.form_data_preview?.email" class="text-muted-foreground/60">
        {{ submission.form_data_preview.email }}
      </span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  submission: {
    type: Object,
    required: true,
  },
});
</script>

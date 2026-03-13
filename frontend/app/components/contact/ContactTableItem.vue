<template>
  <div class="flex items-center gap-x-1.5 tracking-tight">
    <Avatar :model="contactModel" class="size-8" :colorful="false" />
    <div class="grid flex-1 text-left text-sm leading-tight">
      <span class="text-foreground truncate font-medium">{{ contact.name }}</span>
      <span v-if="contact.job_title" class="text-muted-foreground truncate text-xs">
        {{ contact.job_title }}
      </span>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  contact: { type: Object, required: true },
});

const contactModel = computed(() => ({
  name: props.contact.name,
}));
</script>

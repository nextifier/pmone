<template>
  <NuxtLink
    :to="`/contacts/${contact.ulid}/edit`"
    class="flex items-center gap-x-2 transition hover:opacity-80"
  >
    <div
      class="bg-muted text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg text-sm font-medium tracking-tight"
    >
      {{ initials }}
    </div>

    <div class="flex flex-col items-start gap-y-0.5 overflow-hidden">
      <p class="truncate text-sm tracking-tight">{{ contact.name }}</p>
      <p v-if="contact.job_title" class="text-muted-foreground truncate text-xs tracking-tight">
        {{ contact.job_title }}
      </p>
      <p
        v-else-if="contact.primary_email"
        class="text-muted-foreground truncate text-xs tracking-tight"
      >
        {{ contact.primary_email }}
      </p>
    </div>
  </NuxtLink>
</template>

<script setup>
const props = defineProps({
  contact: { type: Object, required: true },
});

const initials = computed(() => {
  const parts = (props.contact.name || "").trim().split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }
  return (parts[0]?.[0] || "?").toUpperCase();
});
</script>

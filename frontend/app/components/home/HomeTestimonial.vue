<template>
  <section :class="[background ? 'bg-muted/30' : '', 'py-16']">
    <div class="container">
      <div class="flex flex-col items-start justify-between gap-8 md:flex-row md:items-center">
        <blockquote
          class="text-foreground max-w-3xl text-xl leading-relaxed font-medium tracking-tight lg:text-2xl"
        >
          "{{ quote }}"
        </blockquote>
        <div class="flex shrink-0 items-center gap-4">
          <div class="text-right">
            <p class="text-foreground font-semibold tracking-tight">{{ author }}</p>
            <p class="text-muted-foreground text-sm">{{ role }}</p>
          </div>
          <img :src="avatar" :alt="author" class="bg-muted size-12 rounded-full" />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
defineProps({
  quote: {
    type: String,
    required: true,
  },
  author: {
    type: String,
    required: true,
  },
  role: {
    type: String,
    required: true,
  },
  avatar: {
    type: String,
    required: true,
  },
  background: {
    type: Boolean,
    default: false,
  },
});
</script>

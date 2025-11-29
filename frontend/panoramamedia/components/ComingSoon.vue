<template>
  <div
    class="min-h-screen-offset relative isolate flex flex-col items-center justify-center overflow-hidden text-center"
  >
    <div class="relative w-full">
      <TextFit
        tag="h1"
        text="Coming soon"
        text-classes="text-outline pb-5 !leading-[1] font-extrabold tracking-tighter"
      />

      <span
        v-if="props.title"
        class="border-primary text-primary absolute top-2 right-2 -translate-y-full rotate-6 rounded-full border px-4 py-2.5 tracking-tight backdrop-blur-sm lg:top-8 lg:right-8 2xl:top-16 2xl:right-16"
      >
        {{ props.title }}
      </span>
    </div>

    <p
      v-if="props.description"
      class="max-w-xl text-base tracking-tight text-balance sm:text-lg"
    >
      {{ props.description }}
    </p>
  </div>
</template>

<script setup>
const props = defineProps({
  title: String,
  description: String,
});
</script>

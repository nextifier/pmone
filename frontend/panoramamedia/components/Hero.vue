<template>
  <section id="hero" class="min-h-screen-offset overflow-hidden py-8 lg:py-10">
    <div class="container">
      <div
        class="grid grid-cols-1 gap-x-8 gap-y-3.5 lg:grid-cols-2 lg:items-end"
      >
        <div
          class="flex flex-col items-center gap-y-3 text-center lg:items-start lg:text-left"
        >
          <span class="section-subtitle"
            >Remember that one amazing event? Chances are, we were there.</span
          >
          <h1
            class="text-primary max-w-4xl text-4xl !leading-[1.2] font-semibold tracking-tighter text-balance sm:text-5xl lg:text-6xl 2xl:text-7xl"
          >
            We set the stage for your story to shine.
          </h1>
        </div>

        <div
          class="flex flex-col items-center gap-y-5 text-center lg:ml-auto lg:max-w-xl lg:items-end lg:gap-y-6 lg:text-right"
        >
          <p
            class="text-body text-base !leading-relaxed tracking-tight text-balance sm:text-lg"
          >
            Every event tells a story. Every story needs the perfect stage. We
            create moments that matter.
          </p>
          <div class="flex flex-wrap items-center justify-center gap-2">
            <button
              @click="openInquiryDialog"
              class="bg-primary hover:bg-primary/80 text-primary-foreground flex items-center justify-center gap-1 rounded-xl px-4 py-3 font-semibold tracking-tighter transition active:scale-98"
            >
              Start your event
            </button>
            <NuxtLink
              to="/events"
              class="text-primary hover:bg-muted flex items-center justify-center gap-1 rounded-xl px-4 py-3 font-semibold tracking-tighter transition active:scale-98"
              >See our signature events</NuxtLink
            >
          </div>
        </div>
      </div>

      <HeroVisual class="mt-10" />
    </div>
  </section>
</template>

<script setup>
const uiStore = useUiStore();
const openInquiryDialog = () => {
  uiStore.openInquiryDialog();
};
</script>

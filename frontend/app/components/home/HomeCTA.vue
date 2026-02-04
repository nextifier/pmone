<template>
  <section class="relative overflow-hidden py-20 lg:py-28">
    <div class="relative container">
      <div class="flex flex-col items-center text-center">
        <h2
          class="xs:text-5xl max-w-3xl text-4xl leading-tight font-semibold tracking-tighter lg:text-6xl"
        >
          Ready to simplify your workflow?
        </h2>
        <p class="mt-4 max-w-xl text-lg tracking-tight">
          Join thousands of event pros who ditched the chaos for a calmer, more organized way of
          working.
        </p>

        <!-- CTA Buttons -->
        <div class="mt-6 flex w-full justify-center gap-2 md:flex-wrap md:gap-3">
          <nuxt-link
            to="/login"
            class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center rounded-xl px-4 py-2 text-base font-medium tracking-tighter transition active:scale-95 md:basis-auto"
            v-ripple
            >Get started</nuxt-link
          >
        </div>

        <!-- Trust Badges -->
        <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
          <div class="text-primary/70 flex items-center gap-2">
            <Icon name="hugeicons:star-circle" class="size-5 shrink-0" />
            <span class="text-sm">5.0 Rating</span>
          </div>
          <div class="text-primary/10 hidden h-4 w-px bg-current sm:block" />
          <p class="text-primary/70 text-sm">Free to start</p>
          <div class="text-primary/10 hidden h-4 w-px bg-current sm:block" />
          <p class="text-primary/70 text-sm">Setup in 5 minutes</p>
        </div>
      </div>
    </div>
  </section>
</template>

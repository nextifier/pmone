<template>
  <section id="hero">
    <div class="container">
      <div class="flex flex-col items-center justify-center pt-24 text-center">
        <h1 class="text-primary text-5xl leading-[1.2]! tracking-tighter text-balance sm:text-6xl">
          One Platform for Every Event.
        </h1>
        <p class="section-description mt-3 text-balance!">
          PM One is a platform to manage all your events in one place.
        </p>

        <div class="mt-6 flex w-full justify-center gap-2 md:flex-wrap md:gap-3">
          <nuxt-link
            to="/login"
            class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center rounded-xl px-4 py-2 text-base font-medium tracking-tighter transition active:scale-95 md:basis-auto"
            v-ripple
            >Get started</nuxt-link
          >
        </div>

        <nuxt-link to="/demo" class="mt-10 w-full">
          <LogoWithGuidelines />
        </nuxt-link>
      </div>
    </div>
  </section>
</template>

<script setup></script>

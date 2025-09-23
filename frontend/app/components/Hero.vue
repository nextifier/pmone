<template>
  <section id="hero">
    <div class="container">
      <div
        class="min-h-screen-offset flex flex-col items-center justify-center text-center"
      >
        <h1 class="section-title">One Platform for Every Event.</h1>
        <p class="section-description mt-3 !text-balance">
          PM One is a platform to manage all your events in one place.
        </p>

        <div
          class="mt-6 flex w-full justify-center gap-2 md:flex-wrap md:gap-3"
        >
          <nuxt-link
            to="/login"
            class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center rounded-xl px-5 py-2.5 font-semibold tracking-tighter transition active:scale-95 md:basis-auto"
            v-ripple
            >Get started</nuxt-link
          >
        </div>
      </div>
    </div>
  </section>
</template>

<script setup></script>

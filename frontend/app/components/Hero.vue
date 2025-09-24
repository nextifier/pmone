<template>
  <section id="hero">
    <div class="container">
      <div class="min-h-screen-offset flex flex-col items-center justify-center text-center">
        <h1
          class="text-primary text-5xl !leading-[1.2] font-semibold tracking-tighter text-balance sm:text-6xl"
        >
          One Platform for Every Event.
        </h1>
        <p class="section-description mt-3 !text-balance">
          PM One is a platform to manage all your events in one place.
        </p>

        <div class="mt-6 flex w-full justify-center gap-2 md:flex-wrap md:gap-3">
          <nuxt-link
            to="/login"
            class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center rounded-xl px-5 py-2.5 font-semibold tracking-tighter transition active:scale-95 md:basis-auto"
            v-ripple
            >Get started</nuxt-link
          >
        </div>

        <div class="mt-10 flex flex-col items-start rounded-xl border p-6">
          <div class="text-sm" v-tippy="$dayjs(buildDate).format('MMMM D, YYYY [at] h:mm A')">
            Built {{ $dayjs(buildDate).fromNow() }}
          </div>

          <pre v-if="$pwa" class="rounded-lg bg-gray-950 p-6 text-left text-sm text-gray-300">
          {{ $pwa }}
        </pre
          >

          <button
            v-if="!$pwa?.isPWAInstalled"
            @click="$pwa?.install()"
            class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center gap-1 rounded-full px-3 py-2 font-semibold tracking-tight transition active:scale-98"
          >
            Install
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const { $pwa } = useNuxtApp();
const { $dayjs } = useNuxtApp();
const buildDate = useAppConfig().buildDate;
</script>

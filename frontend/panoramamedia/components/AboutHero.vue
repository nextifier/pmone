<template>
  <section>
    <div class="sm:container-wider relative isolate">
      <div
        class="min-h-screen-offset relative z-10 grid grid-cols-1 items-end gap-x-10 gap-y-10 overflow-hidden lg:grid-cols-2 xl:grid-cols-3"
      >
        <div
          class="flex flex-col px-4 pt-8 sm:px-0 lg:pt-16 xl:order-1 xl:pt-0 xl:pb-20"
        >
          <h1
            class="text-6xl !leading-[1] font-extrabold tracking-tighter xl:text-7xl"
          >
            We Believe Great Companies are Built on
            <span
              class="inline-flex bg-linear-to-r from-yellow-400 via-orange-600 to-red-600 bg-clip-text text-transparent dark:from-yellow-300"
              >Great Teams</span
            >
          </h1>
        </div>

        <div class="order-last h-full lg:col-span-2 xl:order-2 xl:col-span-1">
          <NuxtImg
            src="/img/about-hero-img.jpg"
            alt=""
            class="bg-muted pointer-events-none relative z-10 size-full object-cover select-none"
            width="1346"
            height="2116"
            sizes="100vw lg:800px"
            format="webp"
            loading="lazy"
          />
        </div>

        <div class="flex flex-col gap-y-2 px-4 sm:px-0 xl:order-3 xl:pb-20">
          <span
            class="text-gradient-accent text-base font-semibold tracking-tighter"
            >Welcome to Panorama Events!</span
          >
          <div
            class="text-primary/50 space-y-4 text-xl font-medium tracking-tighter sm:text-2xl 2xl:text-3xl"
          >
            <p>
              For over 20 years, we've been orchestrating memorable events. But
              we realized something crucial along the way: a truly great
              corporate outing isn’t just a break from the office,
              <span class="text-primary font-bold"
                >it's a powerful opportunity to build a stronger, happier, and
                more engaged team.</span
              >
            </p>
            <p>
              That's why we’ve dedicated ourselves to one single mission:
              crafting HR-driven corporate outings that deliver real, lasting
              impact.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup></script>

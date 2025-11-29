<template>
  <section>
    <div class="container">
      <div class="flex flex-col items-center text-center">
        <h2 class="section-title">
          Why Panorama Events? It’s All About
          <span class="text-gradient-accent font-serif"
            >Your Team’s Success.</span
          >
        </h2>
      </div>

      <div
        class="mt-8 grid grid-cols-1 gap-x-2 gap-y-4 sm:grid-cols-2 lg:grid-cols-4"
      >
        <div
          v-for="(item, index) in items"
          :key="index"
          class="bg-muted/50 flex flex-col items-start gap-y-4 rounded-2xl px-6 py-8 transition"
        >
          <Icon
            v-if="item.iconName"
            :name="item.iconName"
            class="text-primary size-8"
          />

          <span
            class="text-primary text-lg font-semibold tracking-tighter sm:text-xl"
            >{{ item.title }}</span
          >
          <p class="text-primary/70 tracking-tight">{{ item.description }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const items = [
  {
    title: "HR-Smart Design: Outings with Purpose & Real Impact",
    description:
      "We design outings based on your unique HR goals, ensuring every activity purposefully boosts engagement and builds a stronger, more connected team.",
    iconName: "hugeicons:healtcare",
  },
  {
    title: "Backed by Panorama Group: Seamless & Reliable Nationwide",
    description:
      "As part of Panorama Group, we offer a vast network for seamless travel, top accommodation, and reliable logistics across Indonesia, giving you total peace of mind.",
    iconName: "hugeicons:building-03",
  },
  {
    title: "Proven Success: Decades of Happy Clients & Thriving Teams",
    description:
      "Our 20+ years, 450+ successful events, and 280+ satisfied clients (energizing 50,000+ participants!) prove we’re a trusted partner for memorable and effective outings.",
    iconName: "hugeicons:checkmark-badge-01",
  },
  {
    title: "Effortless End-to-End Service: You Relax, We Deliver Excellence",
    description:
      "From initial concept to post-event recap, we expertly handle every single detail, ensuring a completely worry-free experience so you can simply relax and enjoy your team's journey.",
    iconName: "hugeicons:rocket-01",
  },
];
</script>

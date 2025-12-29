<template>
  <section class="border-border/50 border-y py-16">
    <div class="container">
      <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
        <div v-for="stat in stats" :key="stat.label" class="text-center">
          <p class="text-primary text-4xl font-bold tracking-tighter lg:text-5xl">
            {{ stat.value }}
          </p>
          <p class="text-muted-foreground mt-2 text-sm tracking-tight">{{ stat.label }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const stats = [
  { value: "10K+", label: "Links created" },
  { value: "1M+", label: "Clicks tracked" },
  { value: "500+", label: "Active projects" },
  { value: "99.9%", label: "Uptime" },
];
</script>

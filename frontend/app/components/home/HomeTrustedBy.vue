<template>
  <section class="border-border/50 border-y py-12">
    <div class="container">
      <p class="text-muted-foreground mb-8 text-center text-sm font-medium tracking-tight">
        Trusted by event pros and creators across Indonesia
      </p>
      <div class="flex flex-wrap items-center justify-center gap-x-12 gap-y-6">
        <div
          v-for="logo in logos"
          :key="logo"
          class="text-muted-foreground/60 text-lg font-semibold tracking-tight"
        >
          {{ logo }}
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const logos = [
  "TechCorp",
  "StartupXYZ",
  "MediaGroup",
  "EventPro",
  "CreativeStudio",
  "DigitalAgency",
];
</script>

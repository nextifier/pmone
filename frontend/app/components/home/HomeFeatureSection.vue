<template>
  <section :class="[background ? 'bg-muted/30' : '', 'py-20 lg:py-32']">
    <div class="container">
      <div class="mb-12 lg:mb-16">
        <div class="flex items-center gap-2">
          <span class="flex size-6 items-center justify-center rounded-md" :class="iconBgClass">
            <Icon :name="icon" class="size-3.5 text-white" />
          </span>
          <span class="text-sm font-semibold tracking-tight">{{ badge }}</span>
        </div>
        <h2 class="section-title mt-4 max-w-xl">
          <span v-html="title" />
        </h2>
        <p class="section-description mt-4 max-w-xl">{{ description }}</p>
        <NuxtLink
          :to="link"
          class="border-border hover:bg-muted mt-6 inline-flex h-10 items-center justify-center rounded-lg border px-5 text-sm font-medium tracking-tight transition"
        >
          {{ linkText }}
        </NuxtLink>
      </div>

      <!-- Feature Cards Grid -->
      <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <div v-for="feature in features" :key="feature.title" class="group relative">
          <div class="bg-card ring-border rounded-xl p-6 ring-1 transition hover:shadow-md">
            <div class="mb-4">
              <Icon
                :name="feature.icon"
                class="text-muted-foreground size-5 transition"
                :class="hoverColorClass"
              />
            </div>
            <h3 class="text-foreground font-semibold tracking-tight">{{ feature.title }}</h3>
            <p class="text-muted-foreground mt-2 text-sm leading-relaxed">
              {{ feature.description }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const props = defineProps({
  badge: {
    type: String,
    required: true,
  },
  icon: {
    type: String,
    required: true,
  },
  iconBgClass: {
    type: String,
    required: true,
  },
  hoverColorClass: {
    type: String,
    default: "group-hover:text-orange-500",
  },
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  link: {
    type: String,
    required: true,
  },
  linkText: {
    type: String,
    required: true,
  },
  features: {
    type: Array,
    required: true,
  },
  background: {
    type: Boolean,
    default: false,
  },
});
</script>

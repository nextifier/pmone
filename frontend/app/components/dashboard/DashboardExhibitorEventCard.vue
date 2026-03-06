<template>
  <div class="border-border bg-card rounded-xl border">
    <!-- Event Info Header (always visible) -->
    <div class="flex items-center gap-3 px-4 py-3 sm:px-5 sm:py-4">
      <img
        v-if="be.event.poster_image?.sm"
        :src="be.event.poster_image.sm"
        :alt="be.event.title"
        class="size-10 shrink-0 rounded-lg object-cover"
      />
      <div
        v-else
        class="bg-muted text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg"
      >
        <Icon name="hugeicons:calendar-03" class="size-5" />
      </div>
      <div class="min-w-0 flex-1">
        <h3 class="truncate text-sm font-medium tracking-tight sm:text-base">{{ be.event.title }}</h3>
        <div class="text-muted-foreground flex flex-wrap items-center gap-x-1.5 text-xs tracking-tight sm:text-sm">
          <span v-if="be.event.date_label">{{ be.event.date_label }}</span>
          <span v-if="be.event.date_label && be.event.location" class="text-muted-foreground/40">·</span>
          <span v-if="be.event.location">{{ be.event.location }}</span>
        </div>
      </div>
      <div class="hidden text-right sm:block">
        <p class="text-sm font-medium tracking-tight">{{ be.brand.name }}</p>
        <p v-if="be.booth_number" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Booth {{ be.booth_number }} - {{ be.booth_type_label }}
        </p>
      </div>
    </div>

    <!-- Mobile brand info -->
    <div class="border-border flex items-center gap-2 border-t px-4 py-2.5 sm:hidden">
      <span class="text-xs font-medium tracking-tight">{{ be.brand.name }}</span>
      <span v-if="be.booth_number" class="text-muted-foreground text-xs tracking-tight">
        Booth {{ be.booth_number }} - {{ be.booth_type_label }}
      </span>
    </div>

    <!-- Key deadlines if any -->
    <div
      v-if="deadlines.length"
      class="border-border flex flex-wrap gap-3 border-t px-4 py-2.5 sm:px-5"
    >
      <div
        v-for="dl in deadlines"
        :key="dl.label"
        class="flex items-center gap-1.5 text-xs tracking-tight sm:text-sm"
        :class="dl.urgent ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
      >
        <Icon :name="dl.icon" class="size-3.5" />
        <span>{{ dl.label }}: {{ dl.date }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  be: { type: Object, required: true },
});

function formatDeadline(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

function isUrgent(dateStr) {
  if (!dateStr) return false;
  const deadline = new Date(dateStr);
  const daysLeft = Math.ceil((deadline - new Date()) / (1000 * 60 * 60 * 24));
  return daysLeft > 0 && daysLeft <= 7;
}

const deadlines = computed(() => {
  const items = [];
  if (props.be.promotion_post_deadline) {
    items.push({
      label: "Promotion",
      date: formatDeadline(props.be.promotion_post_deadline),
      icon: "hugeicons:image-02",
      urgent: isUrgent(props.be.promotion_post_deadline),
    });
  }
  if (props.be.order_form_deadline) {
    items.push({
      label: "Order",
      date: formatDeadline(props.be.order_form_deadline),
      icon: "hugeicons:shopping-cart-01",
      urgent: isUrgent(props.be.order_form_deadline),
    });
  }
  return items;
});
</script>

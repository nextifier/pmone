<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:calendar-03" class="size-5 sm:size-6" />
      <h1 class="page-title">My Events</h1>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-4">
      <div v-for="i in 3" :key="i" class="border-border bg-card animate-pulse rounded-xl border p-5">
        <div class="flex gap-4">
          <div class="bg-muted aspect-4/5 w-16 rounded-lg" />
          <div class="flex-1 space-y-2">
            <div class="bg-muted h-5 w-48 rounded" />
            <div class="bg-muted h-4 w-32 rounded" />
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-else-if="!events.length"
      class="border-border rounded-xl border py-16 text-center"
    >
      <div class="bg-muted mx-auto flex size-12 items-center justify-center rounded-full">
        <Icon name="hugeicons:calendar-03" class="text-muted-foreground size-6" />
      </div>
      <p class="text-muted-foreground mt-4 text-sm tracking-tight">No events found.</p>
    </div>

    <!-- Event cards -->
    <div v-else class="space-y-4">
      <div
        v-for="event in events"
        :key="event.id"
        class="border-border bg-card overflow-hidden rounded-xl border"
      >
        <!-- Event header -->
        <div class="flex gap-4 p-4 sm:p-5">
          <!-- Poster image -->
          <div class="aspect-4/5 w-16 shrink-0 overflow-hidden rounded-lg sm:w-20">
            <img
              v-if="event.poster_image?.sm"
              :src="event.poster_image.sm"
              :alt="event.title"
              class="size-full object-cover"
            />
            <div v-else class="bg-muted text-muted-foreground flex size-full items-center justify-center">
              <Icon name="hugeicons:calendar-03" class="size-6" />
            </div>
          </div>

          <!-- Event info -->
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <h2 class="truncate text-base font-medium tracking-tight sm:text-lg">{{ event.title }}</h2>
              <span
                v-if="event.is_active"
                class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium tracking-tight text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
              >
                Active
              </span>
              <span
                v-else-if="isUpcoming(event)"
                class="shrink-0 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium tracking-tight text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
              >
                Upcoming
              </span>
            </div>

            <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
              <span v-if="event.date_label" class="text-muted-foreground flex items-center gap-1.5 text-xs tracking-tight sm:text-sm">
                <Icon name="hugeicons:calendar-03" class="size-3.5 shrink-0" />
                {{ event.date_label }}
              </span>
              <span v-if="event.location" class="text-muted-foreground flex items-center gap-1.5 text-xs tracking-tight sm:text-sm">
                <Icon name="hugeicons:location-01" class="size-3.5 shrink-0" />
                {{ event.location }}
              </span>
              <span v-if="event.venue" class="text-muted-foreground flex items-center gap-1.5 text-xs tracking-tight sm:text-sm">
                <Icon name="hugeicons:building-03" class="size-3.5 shrink-0" />
                {{ event.venue }}
              </span>
            </div>

            <!-- Brand count summary -->
            <p class="text-muted-foreground mt-2 text-xs tracking-tight sm:text-sm">
              {{ event.brands.length }} brand{{ event.brands.length > 1 ? "s" : "" }} participating
            </p>
          </div>
        </div>

        <!-- Brands list -->
        <div class="border-border border-t">
          <div
            v-for="(brand, idx) in event.brands"
            :key="brand.brand_event_id"
            class="flex items-center gap-3 px-4 py-3 sm:px-5"
            :class="idx > 0 ? 'border-border border-t' : ''"
          >
            <!-- Brand logo -->
            <img
              v-if="brand.brand_logo?.sm"
              :src="brand.brand_logo.sm"
              :alt="brand.name"
              class="size-9 shrink-0 rounded-lg object-cover sm:size-10"
            />
            <div
              v-else
              class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg sm:size-10"
            >
              <Icon name="hugeicons:store-02" class="size-4" />
            </div>

            <!-- Brand info -->
            <div class="min-w-0 flex-1">
              <NuxtLink
                :to="`/brands/${brand.slug}`"
                class="text-sm font-medium tracking-tight transition hover:opacity-80"
              >
                {{ brand.name }}
              </NuxtLink>
              <div class="text-muted-foreground flex flex-wrap items-center gap-x-3 text-xs tracking-tight sm:text-sm">
                <span v-if="brand.booth_number">
                  Booth {{ brand.booth_number }}
                  <span v-if="brand.booth_type_label" class="text-muted-foreground/60">- {{ brand.booth_type_label }}</span>
                </span>
                <span v-if="brand.promotion_posts_count > 0" class="flex items-center gap-1">
                  <Icon name="hugeicons:image-02" class="size-3 shrink-0" />
                  {{ brand.promotion_posts_count }} post{{ brand.promotion_posts_count > 1 ? "s" : "" }}
                </span>
                <span v-if="brand.orders_count > 0" class="flex items-center gap-1">
                  <Icon name="hugeicons:shopping-bag-01" class="size-3 shrink-0" />
                  {{ brand.orders_count }} order{{ brand.orders_count > 1 ? "s" : "" }}
                </span>
              </div>
            </div>

            <!-- Actions -->
            <NuxtLink
              :to="`/brands/${brand.slug}`"
              class="border-border hover:bg-muted shrink-0 rounded-lg border px-3 py-1.5 text-xs font-medium tracking-tight transition sm:text-sm"
            >
              View Brand
            </NuxtLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const client = useSanctumClient();

const events = ref([]);
const loading = ref(true);

function isUpcoming(event) {
  if (!event.start_date) return false;
  return new Date(event.start_date) > new Date();
}

onMounted(async () => {
  try {
    const res = await client(`/api/exhibitor/events`);
    events.value = res.data || [];
  } catch (e) {
    console.error("Failed to load events:", e);
  } finally {
    loading.value = false;
  }
});
</script>

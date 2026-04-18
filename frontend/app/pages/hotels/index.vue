<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:building-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Hotels</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <NuxtLink
          v-if="canCreate"
          to="/hotels/create"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-1 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="lucide:plus" class="size-4 shrink-0" />
          <span>Add Hotel</span>
        </NuxtLink>
      </div>
    </div>

    <div v-if="pending" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <div v-else-if="error" class="text-destructive text-sm tracking-tight">Failed to load hotels.</div>

    <div v-else-if="!hotels.length" class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight">
      No hotels yet. Add your first hotel to get started.
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <NuxtLink
        v-for="hotel in hotels"
        :key="hotel.id"
        :to="`/hotels/${hotel.slug}`"
        class="bg-card hover:bg-muted/50 group rounded-lg border p-4 transition tracking-tight"
      >
        <div class="bg-muted aspect-3/2 mb-3 overflow-hidden rounded-md">
          <img
            v-if="hotel.featured?.md"
            :src="hotel.featured.md"
            :alt="hotel.name"
            class="size-full object-cover"
          />
          <div v-else class="text-muted-foreground flex size-full items-center justify-center">
            <Icon name="hugeicons:building-01" class="size-10" />
          </div>
        </div>

        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <h3 class="text-base font-semibold tracking-tight truncate">{{ hotel.name }}</h3>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">{{ hotel.city || "-" }}</p>
          </div>
          <span
            :class="[
              'inline-flex items-center rounded-full px-2 py-0.5 text-xs tracking-tight',
              hotel.is_active ? 'bg-success/15 text-success-foreground' : 'bg-muted text-muted-foreground',
            ]"
          >
            {{ hotel.is_active ? "Active" : "Inactive" }}
          </span>
        </div>

        <div class="text-muted-foreground mt-3 flex items-center justify-between text-xs sm:text-sm tracking-tight">
          <span>{{ hotel.room_types_count ?? 0 }} room types</span>
          <span>Commission {{ Number(hotel.commission_rate).toFixed(2) }}%</span>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["hotels.read"],
  layout: "app",
});

usePageMeta(null, {
  title: "Hotels",
});

const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("hotels.create"));

const { data, pending, error, refresh } = await useLazySanctumFetch("/api/hotels?per_page=50", {
  key: "hotels-list",
});

const hotels = computed(() => data.value?.data ?? []);
</script>

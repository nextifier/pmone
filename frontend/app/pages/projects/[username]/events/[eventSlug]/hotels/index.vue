<template>
  <div class="space-y-6 pb-16">
    <div class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:building-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Hotels</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <NuxtLink
          v-if="canCreate"
          :to="`${eventBase}/hotels/create`"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-1 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="lucide:plus" class="size-4 shrink-0" />
          <span>Add Hotel</span>
        </NuxtLink>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <Input
        v-model="search"
        type="search"
        placeholder="Search name or city…"
        class="w-full sm:w-64"
      />
      <Select v-model="statusFilter">
        <SelectTrigger class="w-40"><SelectValue placeholder="Status" /></SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All statuses</SelectItem>
          <SelectItem value="active">Active</SelectItem>
          <SelectItem value="inactive">Inactive</SelectItem>
        </SelectContent>
      </Select>
      <Select v-model="sort">
        <SelectTrigger class="w-44"><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value="-created_at">Newest first</SelectItem>
          <SelectItem value="created_at">Oldest first</SelectItem>
          <SelectItem value="name">Name A-Z</SelectItem>
          <SelectItem value="-name">Name Z-A</SelectItem>
          <SelectItem value="city">City A-Z</SelectItem>
        </SelectContent>
      </Select>
      <Button variant="ghost" size="sm" @click="resetFilters">Reset</Button>
    </div>

    <div v-if="pending" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <div v-else-if="error" class="text-destructive text-sm tracking-tight">Failed to load hotels.</div>

    <div v-else-if="!hotels.length" class="text-muted-foreground rounded-md border border-dashed py-10 text-center text-sm tracking-tight">
      <template v-if="search || statusFilter !== 'all'">
        No hotels match your filters.
      </template>
      <template v-else>
        No hotels yet. Add your first hotel to get started.
      </template>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <NuxtLink
        v-for="hotel in hotels"
        :key="hotel.id"
        :to="`${eventBase}/hotels/${hotel.slug}`"
        class="bg-card hover:bg-muted/50 group rounded-lg border p-4 transition tracking-tight"
      >
        <div class="bg-muted aspect-3/2 mb-3 overflow-hidden rounded-md">
          <img
            v-if="hotel.featured?.md"
            :src="hotel.featured.md"
            :alt="hotel.name"
            class="size-full object-cover"
            loading="lazy"
            decoding="async"
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

    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2 pt-4">
      <Button
        variant="outline"
        size="sm"
        :disabled="currentPage <= 1"
        @click="currentPage = currentPage - 1"
      >
        Previous
      </Button>
      <span class="text-muted-foreground text-sm tracking-tight">
        Page {{ currentPage }} / {{ meta.last_page }}
      </span>
      <Button
        variant="outline"
        size="sm"
        :disabled="currentPage >= meta.last_page"
        @click="currentPage = currentPage + 1"
      >
        Next
      </Button>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { computed, ref, watch } from "vue";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["hotels.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();

const eventBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}`
);

usePageMeta(null, {
  title: computed(() => `Hotels · ${props.event?.title || "Event"}`),
});

const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("hotels.create"));

const search = ref("");
const statusFilter = ref("all");
const sort = ref("-created_at");
const currentPage = ref(1);
const PER_PAGE = 24;

let searchDebounce = null;
const debouncedSearch = ref("");
watch(search, (value) => {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    debouncedSearch.value = value;
    currentPage.value = 1;
  }, 300);
});

watch([statusFilter, sort], () => {
  currentPage.value = 1;
});

const queryUrl = computed(() => {
  const params = new URLSearchParams();
  params.set("per_page", String(PER_PAGE));
  params.set("page", String(currentPage.value));
  params.set("sort", sort.value);
  if (debouncedSearch.value) params.set("filter_search", debouncedSearch.value);
  if (statusFilter.value === "active") params.set("filter_is_active", "1");
  if (statusFilter.value === "inactive") params.set("filter_is_active", "0");
  return `/api/events/${props.event?.id}/hotels?${params.toString()}`;
});

const { data, pending, error } = await useLazySanctumFetch(() => queryUrl.value, {
  key: () => `hotels-list-${props.event?.id}-${debouncedSearch.value}-${statusFilter.value}-${sort.value}-${currentPage.value}`,
});

const hotels = computed(() => data.value?.data ?? []);
const meta = computed(() => data.value?.meta ?? null);

const resetFilters = () => {
  search.value = "";
  debouncedSearch.value = "";
  statusFilter.value = "all";
  sort.value = "-created_at";
  currentPage.value = 1;
};
</script>

<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-5xl xl:max-w-6xl">
    <div class="flex items-center justify-between gap-2">
      <div class="flex items-center gap-x-2.5 min-w-0">
        <NuxtLink to="/hotels" class="hover:bg-muted text-muted-foreground inline-flex size-8 items-center justify-center rounded-md shrink-0">
          <Icon name="lucide:arrow-left" class="size-4" />
        </NuxtLink>
        <h1 class="page-title truncate">{{ hotel?.name ?? "Hotel" }}</h1>
      </div>

      <NuxtLink
        v-if="canEdit && hotel"
        :to="`/hotels/${slug}/edit`"
        class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2.5 py-1.5 text-sm tracking-tight active:scale-98"
      >
        <Icon name="lucide:pencil" class="size-4 shrink-0" />
        Edit
      </NuxtLink>
    </div>

    <div v-if="pending" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <div v-else-if="hotel" class="space-y-6">
      <Tabs default-value="overview">
        <TabsList>
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="rooms">Room Types</TabsTrigger>
          <TabsTrigger value="allotments">Allotments</TabsTrigger>
          <TabsTrigger value="transfers">Transfer Options</TabsTrigger>
        </TabsList>

        <TabsContent value="overview" class="space-y-4 pt-4">
          <div v-if="hotel.featured?.lg" class="bg-muted overflow-hidden rounded-md">
            <img :src="hotel.featured.lg" :alt="hotel.name" class="aspect-video w-full object-cover" />
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-md border p-4">
              <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Location</p>
              <p class="text-sm tracking-tight mt-1">{{ hotel.address || "-" }}</p>
              <p class="text-sm tracking-tight">{{ [hotel.city, hotel.country].filter(Boolean).join(", ") }}</p>
            </div>
            <div class="rounded-md border p-4">
              <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Contact</p>
              <p class="text-sm tracking-tight mt-1">{{ hotel.contact_email || "-" }}</p>
              <p class="text-sm tracking-tight">{{ hotel.contact_phone || "-" }}</p>
            </div>
            <div class="rounded-md border p-4">
              <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Check-in / Check-out</p>
              <p class="text-sm tracking-tight mt-1">{{ hotel.check_in_time?.slice(0,5) }} / {{ hotel.check_out_time?.slice(0,5) }}</p>
            </div>
            <div class="rounded-md border p-4">
              <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Commission / Tax / Service</p>
              <p class="text-sm tracking-tight mt-1">
                {{ Number(hotel.commission_rate).toFixed(2) }}% / {{ Number(hotel.tax_percentage).toFixed(2) }}% / {{ Number(hotel.service_charge_percentage).toFixed(2) }}%
              </p>
            </div>
          </div>

          <div v-if="hotel.description" class="rounded-md border p-4">
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight mb-2">Description</p>
            <p class="text-sm tracking-tight whitespace-pre-line">{{ hotel.description }}</p>
          </div>

          <div v-if="hotel.gallery?.length" class="space-y-2">
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">Gallery ({{ hotel.gallery.length }})</p>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
              <div v-for="img in hotel.gallery" :key="img.id" class="bg-muted aspect-square overflow-hidden rounded">
                <img :src="img.sm || img.url" :alt="img.name" class="size-full object-cover" />
              </div>
            </div>
          </div>
        </TabsContent>

        <TabsContent value="rooms" class="pt-4">
          <RoomTypesPanel :hotel-slug="slug" />
        </TabsContent>

        <TabsContent value="allotments" class="pt-4">
          <AllotmentsPanel :hotel-slug="slug" />
        </TabsContent>

        <TabsContent value="transfers" class="pt-4">
          <TransferOptionsPanel :hotel-slug="slug" />
        </TabsContent>
      </Tabs>
    </div>
  </div>
</template>

<script setup>
import RoomTypesPanel from "@/components/hotel/RoomTypesPanel.vue";
import AllotmentsPanel from "@/components/hotel/AllotmentsPanel.vue";
import TransferOptionsPanel from "@/components/hotel/TransferOptionsPanel.vue";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["hotels.read"],
  layout: "app",
});

const route = useRoute();
const slug = computed(() => route.params.slug);

const { hasPermission } = usePermission();
const canEdit = computed(() => hasPermission("hotels.update"));

const { data, pending } = await useLazySanctumFetch(() => `/api/hotels/${slug.value}`, {
  key: () => `hotel-detail-${slug.value}`,
});

const hotel = computed(() => data.value?.data);

usePageMeta(null, {
  title: computed(() => `${hotel.value?.name ?? "Hotel"} · Hotels`),
});
</script>

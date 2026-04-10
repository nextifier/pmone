<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <!-- Back + Title -->
    <div class="flex items-center gap-x-3">
      <NuxtLink
        to="/partners"
        class="text-muted-foreground hover:text-foreground flex size-8 items-center justify-center rounded-lg transition"
      >
        <Icon name="hugeicons:arrow-left-01" class="size-5" />
      </NuxtLink>
      <div class="min-w-0 flex-1">
        <h2 class="truncate text-lg font-medium tracking-tight">
          {{ partner?.name || "Partner" }}
        </h2>
      </div>
      <NuxtLink
        v-if="canEdit"
        :to="`/partners/${route.params.slug}/edit`"
        class="border-border hover:bg-muted inline-flex items-center gap-x-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98"
      >
        <Icon name="hugeicons:edit-02" class="size-4" />
        Edit
      </NuxtLink>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <template v-else-if="partner">
      <!-- Partner Info Card -->
      <div class="border-border rounded-xl border p-5">
        <div class="flex items-start gap-x-4">
          <Avatar
            :model="{ name: partner.name, profile_image: partner.partner_logo }"
            class="size-14 shrink-0"
            rounded="rounded-xl"
            :colorful="false"
          />
          <div class="min-w-0 flex-1 space-y-2">
            <div class="flex items-center gap-x-2">
              <h3 class="truncate font-medium tracking-tight">{{ partner.name }}</h3>
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium capitalize tracking-tight"
                :class="{
                  'bg-success/10 text-success-foreground': partner.status === 'active',
                  'bg-muted text-muted-foreground': partner.status === 'inactive',
                }"
              >
                {{ partner.status }}
              </span>
            </div>

            <div v-if="partner.website_url" class="flex items-center gap-x-1.5">
              <Icon name="hugeicons:link-02" class="text-muted-foreground size-3.5 shrink-0" />
              <a
                :href="partner.website_url"
                target="_blank"
                class="text-primary truncate text-sm tracking-tight hover:underline"
              >
                {{ partner.website_url.replace(/^https?:\/\//, "") }}
              </a>
            </div>

            <p
              v-if="partner.description"
              class="text-muted-foreground text-sm tracking-tight"
            >
              {{ partner.description }}
            </p>

            <div class="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs tracking-tight sm:text-sm">
              <span>{{ partner.visibility }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Associated Events -->
      <div v-if="partner.events?.length" class="space-y-3">
        <h3 class="font-medium tracking-tight">Events</h3>
        <div class="space-y-2">
          <div
            v-for="event in partner.events"
            :key="event.id"
            class="border-border rounded-xl border p-4"
          >
            <div class="space-y-1">
              <h4 class="font-medium tracking-tight">{{ event.title }}</h4>
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="cat in event.categories"
                  :key="cat"
                  class="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-xs tracking-tight"
                >
                  {{ cat }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Metadata -->
      <div class="border-border rounded-xl border p-5">
        <h3 class="mb-3 font-medium tracking-tight">Metadata</h3>
        <div class="text-muted-foreground space-y-2 text-sm tracking-tight">
          <div class="flex justify-between">
            <span>ID</span>
            <span class="font-medium">{{ partner.id }}</span>
          </div>
          <div class="flex justify-between">
            <span>ULID</span>
            <span class="font-mono text-xs">{{ partner.ulid }}</span>
          </div>
          <div class="flex justify-between">
            <span>Slug</span>
            <span class="font-medium">{{ partner.slug }}</span>
          </div>
          <div class="flex justify-between">
            <span>Created</span>
            <span>{{ $dayjs(partner.created_at).format("MMM D, YYYY [at] h:mm A") }}</span>
          </div>
          <div v-if="partner.updated_at" class="flex justify-between">
            <span>Updated</span>
            <span>{{ $dayjs(partner.updated_at).format("MMM D, YYYY [at] h:mm A") }}</span>
          </div>
          <div v-if="partner.created_by" class="flex justify-between">
            <span>Created by</span>
            <span class="font-medium">{{ partner.created_by.name }}</span>
          </div>
          <div v-if="partner.updated_by" class="flex justify-between">
            <span>Updated by</span>
            <span class="font-medium">{{ partner.updated_by.name }}</span>
          </div>
        </div>
      </div>
    </template>

    <!-- Not found -->
    <div v-else class="flex flex-col items-center justify-center gap-y-4 py-20 text-center">
      <Icon name="hugeicons:search-01" class="text-muted-foreground size-12" />
      <div class="space-y-1">
        <h2 class="text-lg font-semibold tracking-tight">Partner not found</h2>
        <p class="text-muted-foreground text-sm tracking-tight">
          The partner you're looking for doesn't exist or has been deleted.
        </p>
      </div>
      <NuxtLink
        to="/partners"
        class="text-primary text-sm font-medium tracking-tight hover:underline"
      >
        Back to Partners
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["partners.read"],
  layout: "app",
});

const route = useRoute();
const { $dayjs } = useNuxtApp();
const client = useSanctumClient();
const { hasPermission } = usePermission();
const canEdit = computed(() => hasPermission("partners.update"));

usePageMeta(null, { title: "Partner" });

const partner = ref(null);
const loading = ref(true);

onMounted(async () => {
  try {
    const response = await client(`/api/partners/${route.params.slug}`);
    partner.value = response.data;
  } catch {
    partner.value = null;
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="flex flex-col gap-y-0">
    <TabNav :tabs="brandTabs" />

    <!-- Loading -->
    <div v-if="pending" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex flex-col items-center justify-center py-20">
      <p class="text-destructive text-sm">Failed to load brand details.</p>
    </div>

    <!-- Content -->
    <div v-else class="pt-6">
      <NuxtPage
        :brand-event="brandEvent"
        :custom-field-definitions="customFieldDefinitions"
        :custom-field-values="customFieldValues"
        @refresh="refresh()"
      />
    </div>
  </div>
</template>

<script setup>
defineProps({ event: Object, project: Object });
const route = useRoute();
const client = useSanctumClient();

const apiUrl = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}`
);

const data = ref(null);
const pending = ref(true);
const error = ref(null);
const brandEvent = computed(() => data.value?.data);
const customFieldDefinitions = computed(() => data.value?.project_custom_field_definitions || []);
const customFieldValues = computed(() => data.value?.brand_custom_fields || {});

async function refresh() {
  pending.value = true;
  error.value = null;
  try {
    data.value = await client(apiUrl.value);
  } catch (e) {
    error.value = e;
  }
  pending.value = false;
}

// Share brand data to AppHeader via useState
const headerBrand = useState("header-brand", () => null);
watch(
  brandEvent,
  (val) => {
    headerBrand.value = val;
  },
  { immediate: true }
);
onBeforeUnmount(() => {
  headerBrand.value = null;
});

const brandBase = computed(
  () =>
    `/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}`
);
const brandTabs = computed(() => [
  { label: "Profile", to: brandBase.value, exact: true },
  { label: "Booth", to: `${brandBase.value}/booth` },
  { label: "Marketing", to: `${brandBase.value}/marketing` },
  { label: "Orders", to: `${brandBase.value}/orders` },
  { label: "Members", to: `${brandBase.value}/members` },
]);

onMounted(() => refresh());
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <div class="flex items-center gap-x-3">
      <BackButton destination="/contacts" :show-label="true" />
    </div>

    <!-- Loading options -->
    <div v-if="loadingOptions" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <FormContact
      v-else
      api-url="/api/contacts"
      method="POST"
      submit-label="Create Contact"
      :contact-type-options="contactTypeOptions"
      :business-category-options="businessCategoryOptions"
      :project-options="projectOptions"
      @saved="handleSaved"
    />
  </div>
</template>

<script setup>
import FormContact from "@/components/contact/FormContact.vue";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["contacts.create"],
  layout: "app",
});

usePageMeta(null, {
  title: "Create Contact",
});

const router = useRouter();
const client = useSanctumClient();

const loadingOptions = ref(true);
const contactTypeOptions = ref([]);
const businessCategoryOptions = ref([]);
const projectOptions = ref([]);

const fetchOptions = async () => {
  try {
    // Contact types are predefined from enum
    contactTypeOptions.value = [
      { value: "exhibitor", label: "Exhibitor" },
      { value: "media-partner", label: "Media Partner" },
      { value: "sponsor", label: "Sponsor" },
      { value: "speaker", label: "Speaker" },
      { value: "vendor", label: "Vendor" },
      { value: "visitor", label: "Visitor" },
      { value: "other", label: "Other" },
    ];

    // Fetch business categories
    try {
      const catRes = await client("/api/contacts-business-categories");
      businessCategoryOptions.value = (catRes.data || []).map((c) => c.name);
    } catch {
      // Silent fail
    }

    // Fetch projects for association
    try {
      const projectRes = await client("/api/projects");
      projectOptions.value = (projectRes.data || []).map((p) => ({
        value: p.id,
        label: p.name,
      }));
    } catch {
      // Projects may not be accessible
    }
  } catch {
    // Silent fail for options
  }
  loadingOptions.value = false;
};

const handleSaved = (data) => {
  if (data?.ulid) {
    router.push(`/contacts/${data.ulid}/edit`);
  } else {
    router.push("/contacts");
  }
};

onMounted(fetchOptions);
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <div class="flex items-center gap-x-3">
      <BackButton destination="/contacts" :show-label="true" />
    </div>

    <!-- Loading -->
    <div v-if="pending" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <template v-else-if="contact">
      <FormContact
        :contact="contact"
        :api-url="`/api/contacts/${ulid}`"
        method="PUT"
        submit-label="Update Contact"
        :contact-type-options="contactTypeOptions"
        :business-category-options="businessCategoryOptions"
        :project-options="projectOptions"
        @saved="fetchContact"
      />
    </template>

    <!-- Not Found -->
    <div v-else class="flex flex-col items-center justify-center gap-3 py-20">
      <div class="bg-muted flex size-14 items-center justify-center rounded-full">
        <Icon name="hugeicons:contact-01" class="text-muted-foreground size-7" />
      </div>
      <p class="text-muted-foreground text-sm">Contact not found</p>
      <NuxtLink to="/contacts" class="text-primary text-sm hover:underline">
        Back to Contacts
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
import FormContact from "@/components/contact/FormContact.vue";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["contacts.update"],
  layout: "app",
});

usePageMeta(null, {
  title: "Edit Contact",
});

const route = useRoute();
const client = useSanctumClient();
const ulid = route.params.ulid;

const pending = ref(true);
const contact = ref(null);
const contactTypeOptions = ref([]);
const businessCategoryOptions = ref([]);
const projectOptions = ref([]);

const fetchContact = async () => {
  try {
    const res = await client(`/api/contacts/${ulid}`);
    contact.value = res.data;
    contactTypeOptions.value = res.contact_type_options || [];
    businessCategoryOptions.value = res.business_category_options || [];

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
  } catch (e) {
    console.error("Failed to fetch contact:", e);
  }
  pending.value = false;
};

onMounted(fetchContact);
</script>

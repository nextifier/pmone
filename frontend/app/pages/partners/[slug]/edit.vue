<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <div class="flex items-center gap-x-3">
      <ButtonBack destination="/partners" :show-label="true" force-destination />
    </div>

    <!-- Loading -->
    <div v-if="pending" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <template v-else-if="partner">
      <!-- Partner Profile Form -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Partner Profile</div>
        </div>
        <div class="frame-panel">
          <form @submit.prevent="handleSave" class="space-y-4">
            <!-- Logo -->
            <div class="space-y-2">
              <Label>Logo</Label>
              <InputFileImage
                v-model="logoFiles"
                v-model:delete-flag="deleteLogo"
                :initial-image="partner.partner_logo"
                allow-svg
                container-class="relative isolate aspect-3/2 max-w-40"
              />
            </div>

            <!-- Name -->
            <div class="space-y-2">
              <Label>Name</Label>
              <Input v-model="form.name" placeholder="Partner name" required />
            </div>

            <!-- Website URL -->
            <div class="space-y-2">
              <Label>Website URL</Label>
              <Input v-model="form.website_url" placeholder="https://example.com" type="url" />
            </div>

            <!-- Description -->
            <div class="space-y-2">
              <Label>Description</Label>
              <Textarea v-model="form.description" placeholder="About this partner" rows="3" />
            </div>

            <!-- Status -->
            <div class="space-y-2">
              <Label>Status</Label>
              <Select v-model="form.status">
                <SelectTrigger>
                  <SelectValue placeholder="Select status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="inactive">Inactive</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex justify-end">
              <button
                type="submit"
                :disabled="saving"
                class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-5 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Spinner v-if="saving" class="size-4" />
                <span v-else>Save</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Metadata -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Metadata</div>
        </div>
        <div class="frame-panel">
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
      </div>

      <!-- Events -->
      <div v-if="partner.events?.length" class="frame">
        <div class="frame-header">
          <div class="frame-title">Associated Events</div>
        </div>
        <div class="frame-panel">
          <div class="divide-border divide-y">
            <div
              v-for="event in partner.events"
              :key="event.id"
              class="flex items-center justify-between gap-x-3 py-2.5 first:pt-0 last:pb-0"
            >
              <div class="min-w-0">
                <div class="truncate text-sm font-medium tracking-tight">{{ event.title }}</div>
                <div class="text-muted-foreground text-xs tracking-tight">
                  {{ event.categories.join(", ") }}
                </div>
              </div>
            </div>
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
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["partners.update"],
  layout: "app",
});

const route = useRoute();
const slug = route.params.slug;
const { $dayjs } = useNuxtApp();
const client = useSanctumClient();

usePageMeta(null, { title: "Edit Partner" });

// Fetch partner
const partner = ref(null);
const pending = ref(true);

const fetchPartner = async () => {
  try {
    pending.value = true;
    const response = await client(`/api/partners/${slug}`);
    partner.value = response.data;
  } catch (err) {
    partner.value = null;
  } finally {
    pending.value = false;
  }
};

onMounted(fetchPartner);

// Form
const form = reactive({
  name: "",
  website_url: "",
  description: "",
  status: "active",
});

const logoFiles = ref([]);
const deleteLogo = ref(false);

// Populate form when partner loads
watch(
  () => partner.value,
  (p) => {
    if (p) {
      form.name = p.name || "";
      form.website_url = p.website_url || "";
      form.description = p.description || "";
      form.status = p.status || "active";
      logoFiles.value = [];
      deleteLogo.value = false;
    }
  },
  { immediate: true },
);

const saving = ref(false);

const handleSave = async () => {
  saving.value = true;
  try {
    const body = {
      name: form.name,
      website_url: form.website_url || null,
      description: form.description || null,
      status: form.status,
    };

    const logoValue = logoFiles.value?.[0];
    if (logoValue && logoValue.startsWith("tmp-")) {
      body.tmp_partner_logo = logoValue;
    } else if (deleteLogo.value) {
      body.delete_partner_logo = true;
    }

    await client(`/api/partners/${slug}`, {
      method: "PUT",
      body,
    });

    toast.success("Partner updated successfully");
    await fetchPartner();
  } catch (err) {
    toast.error("Failed to update partner", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6 lg:max-w-6xl">
    <div class="flex items-center gap-x-3">
      <ButtonBack destination="/brands" :show-label="true" force-destination />
    </div>

    <!-- Loading -->
    <div v-if="pending" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <TabsRoot v-else-if="brand" v-model="activeTab" class="relative contents">
      <div class="grid gap-6 lg:grid-cols-5">
        <!-- Edit column -->
        <TabsContent
          value="edit"
          force-mount
          class="space-y-6 outline-none max-lg:data-[state=inactive]:hidden lg:col-span-3"
        >
          <BrandFormBrandProfile
            :brand="brand"
            :api-url="`/api/brands/${slug}`"
            :show-logo="true"
            :show-status="true"
            :show-categories="true"
            :show-links="true"
            :business-category-options="businessCategoryOptions"
            :custom-field-definitions="customFieldDefinitions"
            :custom-field-initial-values="customFieldValues"
            :live-preview="true"
            :preview-booth-number="previewBoothNumber"
            @update:preview-data="previewData = $event"
            @saved="fetchBrand"
          />

      <!-- Members (PIC) -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Members (PIC)</div>
        </div>
        <div class="frame-panel">
          <div class="space-y-4">
            <!-- Member list -->
            <div v-if="members.length" class="divide-border divide-y">
              <div
                v-for="member in members"
                :key="member.id"
                class="flex items-center justify-between gap-x-3 py-2.5 first:pt-0 last:pb-0"
              >
                <div class="flex items-center gap-x-2.5 overflow-hidden">
                  <Avatar
                    :model="{ name: member.name, profile_image: member.avatar }"
                    class="size-8 shrink-0"
                    rounded="rounded-full"
                  />
                  <div class="min-w-0">
                    <div class="truncate text-sm font-medium tracking-tight">{{ member.name }}</div>
                    <div class="text-muted-foreground truncate text-xs tracking-tight">
                      {{ member.email }}
                    </div>
                  </div>
                </div>
                <Tippy>
                  <button
                    type="button"
                    @click="removeMember(member)"
                    :disabled="removingMember === member.id"
                    class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 shrink-0 rounded-md p-1.5 transition"
                  >
                    <Icon
                      v-if="removingMember === member.id"
                      name="svg-spinners:ring-resize"
                      class="size-4"
                    />
                    <Icon v-else name="hugeicons:delete-02" class="size-4" />
                  </button>
                  <template #content>
                    <span class="text-xs tracking-tight">Remove member</span>
                  </template>
                </Tippy>
              </div>
            </div>
            <p v-else class="text-muted-foreground text-sm tracking-tight">No members yet.</p>

            <!-- Add member -->
            <div class="border-border border-t pt-4">
              <form @submit.prevent="addMember" class="flex items-end gap-x-2">
                <div class="min-w-0 flex-1 space-y-2">
                  <Label for="new_member_email">Add Member</Label>
                  <Input
                    id="new_member_email"
                    v-model="newMemberEmail"
                    type="email"
                    placeholder="email@example.com"
                  />
                </div>
                <Button
                  type="submit"
                  size="sm"
                  :disabled="addingMember || !newMemberEmail.trim()"
                  class="shrink-0"
                >
                  <Icon v-if="addingMember" name="svg-spinners:ring-resize" class="mr-1 size-4" />
                  <Icon v-else name="hugeicons:add-01" class="mr-1 size-4" />
                  Invite
                </Button>
              </form>
              <div class="mt-2 flex items-center gap-x-2">
                <Checkbox id="send_invite_email" v-model="sendLoginEmail" />
                <Label for="send_invite_email" class="text-sm font-normal">Send login email</Label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Brand Metadata -->
      <div v-if="brand" class="frame">
        <div class="frame-header">
          <div class="frame-title">Brand Metadata</div>
        </div>
        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-y-4 sm:grid-cols-2">
            <div>
              <p class="text-muted-foreground text-xs sm:text-sm">ID</p>
              <p class="font-mono text-sm">{{ brand.id }}</p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs sm:text-sm">ULID</p>
              <p class="font-mono text-sm">{{ brand.ulid }}</p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs sm:text-sm">Created</p>
              <p class="text-sm">
                {{ brand.created_at ? $dayjs(brand.created_at).format("MMM D, YYYY [at] h:mm A") : "-" }}
              </p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs sm:text-sm">Last Updated</p>
              <p class="text-sm">
                {{ brand.updated_at ? $dayjs(brand.updated_at).format("MMM D, YYYY [at] h:mm A") : "-" }}
              </p>
            </div>
            <div v-if="brand.created_by">
              <p class="text-muted-foreground text-xs sm:text-sm">Created By</p>
              <p class="text-sm">{{ brand.created_by.name }}</p>
            </div>
            <div v-if="brand.updated_by">
              <p class="text-muted-foreground text-xs sm:text-sm">Updated By</p>
              <p class="text-sm">{{ brand.updated_by.name }}</p>
            </div>
          </div>
        </div>
      </div>
        </TabsContent>

        <!-- Preview column -->
        <TabsContent
          value="preview"
          force-mount
          class="outline-none max-lg:data-[state=inactive]:hidden lg:col-span-2"
        >
          <BrandLivePreview
            :preview="previewData"
            class="lg:sticky lg:top-[var(--navbar-height-desktop)]"
          />
        </TabsContent>
      </div>

      <!-- Mobile pill trigger -->
      <div class="fixed bottom-8 left-1/2 z-50 -translate-x-1/2 lg:hidden">
        <BrandPreviewTabsTrigger />
      </div>
    </TabsRoot>

    <!-- Not Found -->
    <div v-else class="flex flex-col items-center justify-center gap-3 py-20">
      <div class="bg-muted flex size-14 items-center justify-center rounded-full">
        <Icon name="hugeicons:store-02" class="text-muted-foreground size-7" />
      </div>
      <p class="text-muted-foreground text-sm">{{ $t("brands.notFound") }}</p>
      <NuxtLink to="/brands" class="text-primary text-sm hover:underline">
        {{ $t("brands.backToBrands") }}
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
import BrandLivePreview from "@/components/brand/preview/BrandLivePreview.vue";
import BrandPreviewTabsTrigger from "@/components/brand/preview/BrandPreviewTabsTrigger.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { TabsContent, TabsRoot } from "reka-ui";
import { toast } from "vue-sonner";

const { t } = useI18n();
const { $dayjs } = useNuxtApp();

const activeTab = ref("edit");
const previewData = ref({});

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["brands.update"],
  layout: "app",
});

usePageMeta(null, {
  title: "Edit Brand",
});

const route = useRoute();
const client = useSanctumClient();
const slug = route.params.slug;

const pending = ref(true);
const brand = ref(null);
const businessCategoryOptions = ref([]);
const customFieldDefinitions = ref([]);
const customFieldValues = ref({});
const members = ref([]);

// The brand payload isn't scoped to a single event, so booth number is not
// known here; the preview shows a placeholder.
const previewBoothNumber = computed(() => null);

const fetchBrand = async () => {
  try {
    const res = await client(`/api/brands/${slug}`);
    brand.value = res.data;
    members.value = res.data?.members || [];
    businessCategoryOptions.value = res.business_category_options || [];
    customFieldDefinitions.value = res.custom_field_definitions || [];
    customFieldValues.value = res.data?.custom_fields || {};
  } catch (e) {
    console.error("Failed to fetch brand:", e);
  }
  pending.value = false;
};

// Members
const newMemberEmail = ref("");
const sendLoginEmail = ref(false);
const addingMember = ref(false);
const removingMember = ref(null);

async function addMember() {
  if (!newMemberEmail.value.trim()) return;
  addingMember.value = true;
  try {
    const res = await client(`/api/brands/${slug}/members`, {
      method: "POST",
      body: {
        email: newMemberEmail.value.trim(),
        send_login_email: sendLoginEmail.value,
      },
    });
    members.value.push(res.data);
    newMemberEmail.value = "";
    toast.success("Member added");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to add member");
  } finally {
    addingMember.value = false;
  }
}

async function removeMember(member) {
  removingMember.value = member.id;
  try {
    await client(`/api/brands/${slug}/members/${member.id}`, { method: "DELETE" });
    members.value = members.value.filter((m) => m.id !== member.id);
    toast.success("Member removed");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to remove member");
  } finally {
    removingMember.value = null;
  }
}

onMounted(fetchBrand);
</script>

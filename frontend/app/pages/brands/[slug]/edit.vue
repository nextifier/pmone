<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <div class="flex items-center gap-x-3">
      <BackButton destination="/brands" :show-label="true" />
    </div>

    <!-- Loading -->
    <div v-if="pending" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <template v-else-if="brand">
      <BrandFormBrandProfile
        :brand="brand"
        :api-url="`/api/brands/${slug}`"
        :show-logo="true"
        :show-status="true"
        :show-categories="true"
        :business-category-options="businessCategoryOptions"
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
            </div>
          </div>
        </div>
      </div>
    </template>

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
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "vue-sonner";

const { t } = useI18n();

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
const members = ref([]);

const fetchBrand = async () => {
  try {
    const res = await client(`/api/brands/${slug}`);
    brand.value = res.data;
    members.value = res.data?.members || [];
    businessCategoryOptions.value = res.business_category_options || [];
  } catch (e) {
    console.error("Failed to fetch brand:", e);
  }
  pending.value = false;
};

// Members
const newMemberEmail = ref("");
const addingMember = ref(false);
const removingMember = ref(null);

async function addMember() {
  if (!newMemberEmail.value.trim()) return;
  addingMember.value = true;
  try {
    const res = await client(`/api/brands/${slug}/members`, {
      method: "POST",
      body: { email: newMemberEmail.value.trim() },
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

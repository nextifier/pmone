<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <!-- Back button -->
    <ButtonBack :destination="`/link-pages/${slug}`" force-destination />

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div>
        <div class="flex items-center gap-x-2">
          <Icon name="hugeicons:delete-01" class="size-5" />
          <h1 class="text-lg font-semibold tracking-tighter sm:text-xl">Trashed Items</h1>
        </div>
        <p v-if="linkPage" class="text-muted-foreground mt-0.5 text-xs tracking-tight sm:text-sm">
          {{ linkPage.title }}
        </p>
      </div>

      <div class="ml-auto flex items-center gap-1 sm:gap-2">
        <nuxt-link
          :to="`/link-pages/${slug}`"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:link-01" class="size-4 shrink-0" />
          <span>All Items</span>
        </nuxt-link>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="bg-muted h-16 animate-pulse rounded-lg" />
    </div>

    <!-- Empty state -->
    <div v-else-if="trashedItems.length === 0" class="flex flex-col items-center justify-center py-16">
      <Icon name="hugeicons:delete-01" class="text-muted-foreground size-12" />
      <p class="text-muted-foreground mt-3 text-sm tracking-tight">Trash is empty</p>
    </div>

    <!-- Trashed items list -->
    <div v-else class="space-y-2">
      <div
        v-for="item in trashedItems"
        :key="item.id"
        class="border-border flex items-center gap-3 rounded-lg border p-3"
      >
        <div v-if="item.poster" class="w-16 shrink-0">
          <img :src="item.poster.sm || item.poster.url" :alt="item.label" class="w-full rounded-md" />
        </div>

        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium tracking-tight truncate">{{ item.label }}</p>
          <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">{{ item.url }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-1">
          <!-- Restore -->
          <DialogResponsive v-model:open="restoreDialogs[item.id]">
            <template #trigger="{ open }">
              <button @click="open()" class="hover:bg-muted rounded-md p-1.5" title="Restore">
                <Icon name="lucide:undo-2" class="size-4" />
              </button>
            </template>
            <template #default>
              <div class="px-4 pb-10 md:px-6 md:py-5">
                <div class="text-primary text-lg font-semibold tracking-tight">Restore item?</div>
                <p class="text-body mt-1.5 text-sm tracking-tight">
                  This will restore "{{ item.label }}" back to the items list.
                </p>
                <div class="mt-3 flex justify-end gap-2">
                  <button
                    class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                    @click="restoreDialogs[item.id] = false"
                  >
                    Cancel
                  </button>
                  <button
                    @click="handleRestore(item)"
                    class="bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  >
                    Restore
                  </button>
                </div>
              </div>
            </template>
          </DialogResponsive>

          <!-- Delete permanently -->
          <DialogResponsive v-model:open="deleteDialogs[item.id]">
            <template #trigger="{ open }">
              <button @click="open()" class="hover:bg-destructive/10 rounded-md p-1.5" title="Delete permanently">
                <Icon name="lucide:trash" class="text-destructive size-4" />
              </button>
            </template>
            <template #default>
              <div class="px-4 pb-10 md:px-6 md:py-5">
                <div class="text-primary text-lg font-semibold tracking-tight">Are you absolutely sure?</div>
                <p class="text-body mt-1.5 text-sm tracking-tight">
                  This action can't be undone. This will permanently delete "{{ item.label }}".
                </p>
                <div class="mt-3 flex justify-end gap-2">
                  <button
                    class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                    @click="deleteDialogs[item.id] = false"
                  >
                    Cancel
                  </button>
                  <button
                    @click="handleForceDelete(item)"
                    class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98"
                  >
                    Delete Permanently
                  </button>
                </div>
              </div>
            </template>
          </DialogResponsive>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["link_pages.read"],
  layout: "app",
});

const route = useRoute();
const slug = computed(() => route.params.slug);

const client = useSanctumClient();
const loading = ref(true);
const linkPage = ref(null);
const trashedItems = ref([]);

const restoreDialogs = reactive({});
const deleteDialogs = reactive({});

usePageMeta(null, {
  title: computed(() => (linkPage.value ? `Trashed Items · ${linkPage.value.title}` : "Trashed Items")),
});

const fetchData = async () => {
  loading.value = true;
  try {
    const [pageRes, trashRes] = await Promise.all([
      client(`/api/link-pages/${slug.value}`),
      client(`/api/link-pages/${slug.value}/items/trash`),
    ]);
    linkPage.value = pageRes.data;
    trashedItems.value = trashRes.data || [];
  } catch (err) {
    console.error("Failed to fetch trashed items:", err);
    toast.error("Failed to load trashed items");
  } finally {
    loading.value = false;
  }
};

onMounted(fetchData);

const handleRestore = async (item) => {
  restoreDialogs[item.id] = false;
  try {
    await client(`/api/link-pages/${slug.value}/items/trash/${item.id}/restore`, {
      method: "POST",
    });
    trashedItems.value = trashedItems.value.filter((i) => i.id !== item.id);
    toast.success(`"${item.label}" restored`);
  } catch (err) {
    toast.error("Failed to restore item");
  }
};

const handleForceDelete = async (item) => {
  deleteDialogs[item.id] = false;
  try {
    await client(`/api/link-pages/${slug.value}/items/trash/${item.id}`, {
      method: "DELETE",
    });
    trashedItems.value = trashedItems.value.filter((i) => i.id !== item.id);
    toast.success(`"${item.label}" permanently deleted`);
  } catch (err) {
    toast.error("Failed to delete item");
  }
};
</script>

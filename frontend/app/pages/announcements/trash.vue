<template>
  <div class="mx-auto space-y-6 px-4 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:delete-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Announcements Trash</h1>
      </div>
      <NuxtLink
        to="/announcements"
        class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
      >
        <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
        <span>Back</span>
      </NuxtLink>
    </div>

    <div v-if="pending" class="space-y-3">
      <Skeleton v-for="i in 3" :key="i" class="h-14 w-full rounded-md" />
    </div>
    <div
      v-else-if="!items.length"
      class="border-border text-muted-foreground rounded-md border px-4 py-10 text-center text-sm tracking-tight"
    >
      Trash is empty.
    </div>
    <ul v-else class="divide-border border-border divide-y rounded-md border">
      <li
        v-for="item in items"
        :key="item.id"
        class="flex items-center justify-between gap-3 px-4 py-3"
      >
        <div class="flex min-w-0 flex-1 items-center gap-3">
          <Icon v-if="item.icon" :name="item.icon" class="text-muted-foreground size-5 shrink-0" />
          <div class="min-w-0">
            <p class="line-clamp-1 text-sm font-medium tracking-tight">{{ item.title }}</p>
            <p class="text-muted-foreground text-xs tracking-tight">
              Deleted {{ formatDate(item.deleted_at) }}
            </p>
          </div>
        </div>
        <div class="flex shrink-0 gap-2">
          <Button size="sm" variant="outline" @click="restore(item.id)" :disabled="busy === item.id">
            <Icon name="hugeicons:reload" class="size-4" />
            Restore
          </Button>
          <Button
            size="sm"
            variant="outline-destructive"
            @click="forceDelete(item.id)"
            :disabled="busy === item.id"
          >
            <Icon name="lucide:trash-2" class="size-4" />
            Delete forever
          </Button>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["announcements.delete"],
  layout: "app",
});

usePageMeta(null, { title: "Announcements Trash" });

const client = useSanctumClient();
const items = ref([]);
const pending = ref(true);
const busy = ref(null);

function formatDate(date) {
  if (!date) return "";
  return new Date(date).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

async function fetchTrash() {
  pending.value = true;
  try {
    const response = await client("/api/announcements/trash?per_page=100");
    items.value = response?.data || [];
  } catch {
    toast.error("Failed to load trash");
    items.value = [];
  } finally {
    pending.value = false;
  }
}

async function restore(id) {
  busy.value = id;
  try {
    await client(`/api/announcements/trash/${id}/restore`, { method: "POST" });
    toast.success("Announcement restored");
    items.value = items.value.filter((a) => a.id !== id);
  } catch (error) {
    toast.error(error.response?._data?.message || "Failed to restore");
  } finally {
    busy.value = null;
  }
}

async function forceDelete(id) {
  busy.value = id;
  try {
    await client(`/api/announcements/trash/${id}`, { method: "DELETE" });
    toast.success("Announcement permanently deleted");
    items.value = items.value.filter((a) => a.id !== id);
  } catch (error) {
    toast.error(error.response?._data?.message || "Failed to delete");
  } finally {
    busy.value = null;
  }
}

onMounted(fetchTrash);
</script>

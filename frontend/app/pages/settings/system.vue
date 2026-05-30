<template>
  <div class="mx-auto pt-4 pb-16 lg:max-w-4xl">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:settings-02" class="size-5 sm:size-6" />
      <h1 class="page-title">System</h1>
    </div>

    <p class="page-description mt-2 max-w-2xl">
      Maintenance tools for the public event websites. Use these after a deploy that changes how
      public pages look, sort, or what fields they show.
    </p>

    <div class="bg-card mt-6 space-y-4 rounded-xl border p-4 sm:p-5">
      <div class="flex flex-col">
        <div class="bg-muted flex size-8 shrink-0 items-center justify-center rounded-lg">
          <Icon name="hugeicons:database-01" class="size-4" />
        </div>
        <div class="mt-2 text-lg font-medium tracking-tighter">Response Cache</div>
        <p class="text-muted-foreground mt-1 max-w-xl text-sm tracking-tight">
          Public responses (brands, blog, rundown, hotels, etc.) are cached for up to 24 hours. Data
          changes already clear the cache automatically - you only need this right after a deploy
          that changes the response itself, so the public sites pick it up immediately instead of
          waiting for the cache to expire.
        </p>
      </div>

      <DialogResponsive v-model:open="dialogOpen" dialog-max-width="28rem">
        <template #trigger="{ open }">
          <Button @click="open()">
            <Icon name="hugeicons:reload" />
            <span>Clear Response Cache</span>
          </Button>
        </template>

        <template #default>
          <div class="px-4 pb-10 md:px-6 md:py-5">
            <div class="text-primary text-lg font-semibold tracking-tight">
              Clear response cache?
            </div>
            <p class="text-muted-foreground mt-1 text-sm tracking-tight">
              This flushes the entire public response cache. Every public page rebuilds with the
              latest data on the next visit. It is safe to run, but expect a brief spike in load
              while the cache warms up again.
            </p>

            <div class="mt-6 flex justify-end gap-2">
              <Button variant="outline" :disabled="clearing" @click="dialogOpen = false">
                Cancel
              </Button>
              <Button :disabled="clearing" @click="clearCache">
                <Spinner v-if="clearing" class="size-4" />
                <span v-else>Clear Cache</span>
              </Button>
            </div>
          </div>
        </template>
      </DialogResponsive>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["admin.settings"],
  layout: "app",
});

usePageMeta(null, { title: "System · Settings" });

const client = useSanctumClient();
const dialogOpen = ref(false);
const clearing = ref(false);

const clearCache = async () => {
  if (clearing.value) {
    return;
  }

  clearing.value = true;

  try {
    const res = await client("/api/system/response-cache/clear", { method: "POST" });
    dialogOpen.value = false;
    toast.success("Response cache cleared", {
      description:
        res?.message || "Public pages will rebuild with the latest data on the next visit.",
    });
  } catch (err) {
    toast.error("Failed to clear response cache", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    clearing.value = false;
  }
};
</script>

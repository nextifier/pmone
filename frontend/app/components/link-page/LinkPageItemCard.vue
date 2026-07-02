<template>
  <div class="border-border flex gap-3 rounded-lg border p-3 transition-colors">
    <div class="flex shrink-0 pt-1 sm:items-center sm:pt-0">
      <Icon
        name="lucide:grip-vertical"
        class="drag-handle text-muted-foreground size-4 cursor-grab"
      />
    </div>

    <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
      <!-- Poster + Info -->
      <div class="flex min-w-0 flex-1 items-center gap-3">
        <div v-if="item.poster" class="w-16 shrink-0">
          <img
            :src="item.poster.sm || item.poster.url"
            :alt="item.label"
            class="w-full rounded-md"
          />
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium tracking-tight">{{ item.label }}</p>
          <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
            {{ item.url }}
          </p>
        </div>
      </div>

      <!-- Clicks + Actions -->
      <div class="flex shrink-0 items-center gap-4">
        <div
          class="flex shrink-0 items-center gap-1 text-sm tracking-tight"
          v-tippy="'Total clicks'"
        >
          <Icon name="lucide:mouse-pointer-click" class="size-5" />
          <span>Total clicks:</span>
          {{ (item.clicks_count || 0).toLocaleString() }}
        </div>

        <Switch :model-value="item.is_active" @update:model-value="$emit('toggle')" />

        <button @click="$emit('edit')" class="hover:bg-muted rounded-md p-1.5">
          <Icon name="lucide:pencil-line" class="size-4" />
        </button>

        <DialogResponsive v-model:open="deleteDialogOpen">
          <template #trigger="{ open }">
            <button @click="open()" class="hover:bg-destructive/10 rounded-md p-1.5">
              <Icon name="lucide:trash" class="text-destructive size-4" />
            </button>
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-foreground text-lg font-semibold tracking-tight">Are you sure?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will delete the item "{{ item.label }}".
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="deleteDialogOpen = false"
                >
                  Cancel
                </button>
                <button
                  @click="confirmDelete"
                  class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98"
                >
                  Delete
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Switch } from "@/components/ui/switch";

defineProps({
  item: { type: Object, required: true },
});

const emit = defineEmits(["edit", "delete", "toggle"]);

const deleteDialogOpen = ref(false);

function confirmDelete() {
  deleteDialogOpen.value = false;
  emit("delete");
}
</script>

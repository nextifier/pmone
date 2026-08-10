<template>
  <ResponsiveDialog v-model:open="open" title="Rebuild websites">
    <div class="px-4 pt-5 pb-8 md:px-6 md:py-5">
      <div class="text-foreground text-lg font-semibold tracking-tighter">
        {{ title }}
      </div>

      <p class="text-body mt-1.5 text-sm tracking-tight">
        Each site is rebuilt from the latest commit on
        <span class="font-medium">{{ branch }}</span> and republished when it finishes.
        Cloudflare runs six builds at a time and queues the rest.
      </p>

      <ul
        v-if="names.length > 1"
        class="text-body mt-3 max-h-40 overflow-y-auto text-sm tracking-tight"
      >
        <li v-for="name in names" :key="name" class="py-0.5">{{ name }}</li>
      </ul>

      <div class="mt-4 flex justify-end gap-2">
        <Button variant="outline" size="sm" :disabled="loading" @click="open = false">
          Cancel
        </Button>
        <Button size="sm" :disabled="loading" @click="emit('confirm')">
          <Icon v-if="loading" name="svg-spinners:180-ring" class="-ml-1 size-4 shrink-0" />
          Rebuild
        </Button>
      </div>
    </div>
  </ResponsiveDialog>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";

const props = defineProps({
  /** Display names of the sites about to be rebuilt. */
  names: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  /** The branch copy names; only shown, never used to build. */
  branch: { type: String, default: "main" },
});

const open = defineModel("open", { type: Boolean, default: false });

const emit = defineEmits(["confirm"]);

const title = computed(() => {
  if (props.names.length === 1) return `Rebuild ${props.names[0] || "this website"}?`;
  return `Rebuild ${props.names.length} websites?`;
});
</script>

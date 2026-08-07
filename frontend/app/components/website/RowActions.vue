<template>
  <div class="flex items-center justify-end gap-1 *:shrink-0">
    <a
      v-tippy="'Open website'"
      :href="site.url"
      target="_blank"
      rel="noopener noreferrer"
      class="hover:bg-muted inline-flex size-8 items-center justify-center rounded-md"
    >
      <Icon name="hugeicons:link-square-02" class="size-4" />
    </a>

    <button
      v-if="canRebuild && !isRunning"
      v-tippy="'Rebuild'"
      :disabled="busy"
      class="hover:bg-muted inline-flex size-8 items-center justify-center rounded-md disabled:opacity-50"
      @click="rebuild"
    >
      <Icon
        :name="busy ? 'svg-spinners:180-ring' : 'hugeicons:reload'"
        class="size-4"
        :class="{ 'text-warning-foreground': site.needs_rebuild }"
      />
    </button>

    <Popover>
      <PopoverTrigger as-child>
        <button
          class="hover:bg-muted data-[state=open]:bg-muted inline-flex size-8 items-center justify-center rounded-md"
        >
          <Icon name="lucide:ellipsis" class="size-4" />
        </button>
      </PopoverTrigger>
      <PopoverContent align="end" class="w-52 p-1">
        <div class="flex flex-col">
          <PopoverClose as-child>
            <NuxtLink
              :to="`/websites/${site.worker}`"
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
            >
              <Icon name="hugeicons:file-01" class="size-4 shrink-0" />
              <span>Build history &amp; logs</span>
            </NuxtLink>
          </PopoverClose>

          <PopoverClose v-if="canRebuild" as-child>
            <button
              :disabled="busy || isRunning"
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight disabled:opacity-50"
              @click="rebuild"
            >
              <Icon name="hugeicons:reload" class="size-4 shrink-0" />
              <span>Rebuild</span>
            </button>
          </PopoverClose>

          <!-- Only while there is something to stop. Cancelling frees a slot in
               Cloudflare's 6-concurrent queue and saves build minutes. -->
          <PopoverClose v-if="canRebuild && isRunning" as-child>
            <button
              :disabled="busy"
              class="hover:bg-muted text-destructive flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight disabled:opacity-50"
              @click="cancel"
            >
              <Icon name="hugeicons:stop" class="size-4 shrink-0" />
              <span>Cancel build</span>
            </button>
          </PopoverClose>

          <div class="bg-border my-1 h-px" />

          <PopoverClose as-child>
            <a
              :href="site.url"
              target="_blank"
              rel="noopener noreferrer"
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
            >
              <Icon name="hugeicons:link-square-02" class="size-4 shrink-0" />
              <span>Open website</span>
            </a>
          </PopoverClose>

          <PopoverClose as-child>
            <NuxtLink
              :to="`/projects/${site.project}`"
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md px-3 py-2 text-left text-sm tracking-tight"
            >
              <Icon name="hugeicons:folder-01" class="size-4 shrink-0" />
              <span>Open project</span>
            </NuxtLink>
          </PopoverClose>
        </div>
      </PopoverContent>
    </Popover>
  </div>
</template>

<script setup>
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PopoverClose } from "reka-ui";
import { toast } from "vue-sonner";

const props = defineProps({
  site: { type: Object, required: true },
});

const emit = defineEmits(["changed"]);

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canRebuild = computed(() => hasPermission("websites.rebuild"));
const busy = ref(false);

// Anything that is not `stopped` is still in flight — see the status mapping on
// the index page for why Cloudflare needs two fields to say that.
const isRunning = computed(
  () => Boolean(props.site.build) && props.site.build.status !== "stopped",
);

async function rebuild() {
  busy.value = true;
  try {
    const result = await client("/api/websites/rebuild", {
      method: "POST",
      body: { workers: [props.site.worker] },
    });
    toast.success(result.message);
    // Cloudflare needs a moment before the new build appears in its API.
    setTimeout(() => emit("changed"), 3000);
  } catch (e) {
    toast.error(e?.data?.message || "Could not queue the rebuild.");
  } finally {
    busy.value = false;
  }
}

async function cancel() {
  busy.value = true;
  try {
    const result = await client(
      `/api/websites/${props.site.worker}/builds/${props.site.build.uuid}/cancel`,
      { method: "POST" },
    );
    toast.success(result.message);
    setTimeout(() => emit("changed"), 2000);
  } catch (e) {
    toast.error(e?.data?.message || "Could not cancel that build.");
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="flex flex-col">
    <div class="flex flex-none flex-wrap items-center gap-x-3 gap-y-2 pt-4 pb-3">
      <h1 class="text-xl font-semibold tracking-tighter">PreviewPanel</h1>
      <span class="text-muted-foreground text-sm tracking-tight">
        Split at container ≥ {{ PREVIEW_PANEL_SPLIT[threshold].px }}px
      </span>

      <div class="ml-auto flex flex-wrap items-center gap-1">
        <Button
          v-for="key in thresholds"
          :key="key"
          size="xs"
          :variant="threshold === key ? 'default' : 'outline'"
          @click="threshold = key"
        >
          {{ key }}
        </Button>
        <span class="bg-border mx-1 h-4 w-px" />
        <Button
          v-for="key in ratios"
          :key="key"
          size="xs"
          :variant="ratio === key ? 'default' : 'outline'"
          @click="ratio = key"
        >
          {{ key }}
        </Button>
        <span class="bg-border mx-1 h-4 w-px" />
        <Button
          size="xs"
          :variant="side === 'left' ? 'default' : 'outline'"
          @click="side = side === 'left' ? 'right' : 'left'"
        >
          preview left
        </Button>
        <Button
          size="xs"
          :variant="narrow ? 'default' : 'outline'"
          @click="narrow = !narrow"
        >
          narrow container
        </Button>
      </div>
    </div>

    <PreviewPanel
      v-model:tab="tab"
      :threshold="threshold"
      :ratio="ratio"
      :side="side"
      offset="calc(var(--navbar-height-desktop) + 1rem)"
      reloadable
      expandable
      preview-title="pmone.id/demo/preview-panel"
      :class="narrow && 'mx-auto max-w-4xl'"
    >
      <template #edit>
        <div class="mx-auto w-full max-w-2xl space-y-4">
          <div v-for="i in 24" :key="i" class="frame">
            <div class="frame-header">
              <div class="frame-title">Edit block {{ i }}</div>
              <div class="frame-description">
                Scroll this pane and the preview should not move.
              </div>
            </div>
            <div class="frame-panel">
              <Input :placeholder="`Field ${i}`" />
            </div>
          </div>
        </div>
      </template>

      <template #preview-toolbar>
        <Button variant="ghost" size="iconSm" aria-label="Toolbar slot demo">
          <Icon name="lucide:sparkles" class="size-4 shrink-0" />
        </Button>
      </template>

      <template #preview>
        <div class="space-y-3 p-4">
          <component :is="PreviewProbe" />
          <p class="text-muted-foreground text-sm tracking-tight">
            Reload should change the timestamp above.
          </p>
          <div v-for="i in 30" :key="i" class="bg-card rounded-lg border px-3 py-2">
            <p class="text-sm tracking-tight">Preview row {{ i }}</p>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm">Cancel</Button>
          <Button size="sm">Save</Button>
        </div>
      </template>
    </PreviewPanel>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { PreviewPanel, PREVIEW_PANEL_SPLIT } from "@/components/ui/preview-panel";

definePageMeta({
  layout: "app",
});

useHead({ title: "PreviewPanel Demo" });

const thresholds = ["4xl", "5xl", "6xl"];
const ratios = ["1:1", "3:2", "2:1", "2:3"];

const tab = ref("edit");
const threshold = ref("5xl");
const ratio = ref("1:1");
const side = ref("right");
// Caps the panel width without touching the window: the split is a container
// query, so this is the honest way to exercise it.
const narrow = ref(false);

// Lives inside the preview slot so its onMounted proves the reload button
// really tears the subtree down instead of just re-rendering it.
const PreviewProbe = defineComponent({
  setup() {
    const at = ref("…");
    onMounted(() => {
      at.value = new Date().toLocaleTimeString();
    });
    return () => h("p", { class: "text-sm tracking-tight" }, `Mounted at ${at.value}`);
  },
});
</script>

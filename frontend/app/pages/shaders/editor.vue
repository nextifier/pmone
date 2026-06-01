<script setup>
import { ref, computed, onMounted } from "vue";
import { useShaderPresets } from "@/components/shaders-docs/useShaderPresets";
import { generateShaderCode } from "@/components/shaders/generateShaderCode";
import ShaderCanvas from "@/components/shaders/ShaderCanvas.vue";
import ShaderControls from "@/components/shaders/ShaderControls.vue";
import ShaderLayerItem from "@/components/shaders/ShaderLayerItem.vue";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/components/ui/command";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

definePageMeta({ layout: "empty" });
usePageMeta(null, { title: "Editor · Shaders" });

const route = useRoute();
const { getPreset } = useShaderPresets();
const { grouped } = useShaderRegistry();
const { downloadImage, recordVideo } = useShaderExport();

const COLOR_SPACES = ["p3-linear", "srgb"];
const TONE_MAPPINGS = ["linear", "reinhard", "cineon", "aces", "agx", "neutral", "hable", "unreal"];

let idCounter = 0;
const newId = () => `n${idCounter++}`;

function assignIds(nodes) {
  for (const node of nodes) {
    node.id = newId();
    if (node.children?.length) assignIds(node.children);
  }
  return nodes;
}

const tree = ref(assignIds([{ type: "LinearGradient", props: {} }]));
const colorSpace = ref("p3-linear");
const toneMapping = ref("aces");
const activeId = ref(tree.value[0]?.id ?? null);
const title = ref("Untitled");

onMounted(async () => {
  const presetId = route.query.preset;
  if (!presetId) return;
  const preset = await getPreset(presetId);
  if (preset?.config?.components?.length) {
    tree.value = assignIds(structuredClone(preset.config.components));
    colorSpace.value = preset.colorSpace ?? "p3-linear";
    toneMapping.value = preset.toneMapping ?? "aces";
    activeId.value = tree.value[0]?.id ?? null;
    title.value = preset.title ?? "Untitled";
  }
});

const config = computed(() => ({ components: tree.value }));

function findNode(nodes, id) {
  for (const node of nodes) {
    if (node.id === id) return node;
    if (node.children) {
      const found = findNode(node.children, id);
      if (found) return found;
    }
  }
  return null;
}

function findContext(nodes, id) {
  for (let i = 0; i < nodes.length; i++) {
    if (nodes[i].id === id) return { array: nodes, index: i };
    if (nodes[i].children) {
      const ctx = findContext(nodes[i].children, id);
      if (ctx) return ctx;
    }
  }
  return null;
}

const activeNode = computed(() => findNode(tree.value, activeId.value));

function removeLayer(id) {
  const ctx = findContext(tree.value, id);
  if (!ctx) return;
  ctx.array.splice(ctx.index, 1);
  if (activeId.value === id) activeId.value = tree.value[0]?.id ?? null;
}

function moveLayer(id, dir) {
  const ctx = findContext(tree.value, id);
  if (!ctx) return;
  const target = ctx.index + dir;
  if (target < 0 || target >= ctx.array.length) return;
  const [item] = ctx.array.splice(ctx.index, 1);
  ctx.array.splice(target, 0, item);
}

const pickerOpen = ref(false);
function addComponent(name) {
  const node = { id: newId(), type: name, props: {} };
  tree.value.push(node);
  activeId.value = node.id;
  pickerOpen.value = false;
}

const code = computed(() =>
  generateShaderCode(config.value, { colorSpace: colorSpace.value, toneMapping: toneMapping.value }),
);

const copied = ref(false);
async function copyCode() {
  try {
    await navigator.clipboard.writeText(code.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
  } catch {
    /* clipboard blocked */
  }
}

const canvasRef = ref(null);

const imageFormat = ref("jpeg");
const downloading = ref(false);
async function downloadImg() {
  if (!canvasRef.value) return;
  downloading.value = true;
  try {
    await downloadImage(canvasRef.value, {
      format: imageFormat.value,
      filename: title.value || "shader",
    });
  } finally {
    downloading.value = false;
  }
}

const videoSeconds = ref("5");
const recording = ref(false);
const recordProgress = ref(0);
async function recordClip() {
  if (!canvasRef.value) return;
  recording.value = true;
  recordProgress.value = 0;
  try {
    await recordVideo(canvasRef.value, {
      filename: title.value || "shader",
      durationMs: Number(videoSeconds.value) * 1000,
      onProgress: (f) => (recordProgress.value = f),
    });
  } finally {
    recording.value = false;
  }
}

const formatName = (s) => s.replace(/-/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());
</script>

<template>
  <div class="bg-background flex h-svh flex-col">
    <!-- Toolbar -->
    <header class="flex h-14 shrink-0 items-center gap-x-2 border-b px-3">
      <Button to="/shaders" variant="ghost" size="iconSm" class="text-muted-foreground">
        <Icon name="hugeicons:arrow-left-01" />
      </Button>
      <span class="max-w-40 truncate text-sm font-medium tracking-tight">{{ title }}</span>

      <div class="ml-auto flex items-center gap-x-2">
        <Select v-model="colorSpace">
          <SelectTrigger size="sm" class="hidden w-32 sm:flex"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="cs in COLOR_SPACES" :key="cs" :value="cs">{{ cs }}</SelectItem>
          </SelectContent>
        </Select>
        <Select v-model="toneMapping">
          <SelectTrigger size="sm" class="hidden w-28 sm:flex"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="tm in TONE_MAPPINGS" :key="tm" :value="tm">{{ tm }}</SelectItem>
          </SelectContent>
        </Select>
        <Popover>
          <PopoverTrigger as-child>
            <Button variant="outline" size="sm">
              <Icon name="hugeicons:download-04" />
              Export
            </Button>
          </PopoverTrigger>
          <PopoverContent align="end" class="w-72 space-y-4 p-4">
            <div class="space-y-2">
              <p class="text-muted-foreground text-xs font-medium tracking-tight uppercase">Image</p>
              <div class="flex items-center gap-x-2">
                <Select v-model="imageFormat">
                  <SelectTrigger size="sm" class="w-24"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="jpeg">JPG</SelectItem>
                    <SelectItem value="png">PNG</SelectItem>
                    <SelectItem value="webp">WebP</SelectItem>
                  </SelectContent>
                </Select>
                <Button size="sm" class="flex-1" :disabled="downloading" @click="downloadImg">
                  <Icon
                    :name="downloading ? 'hugeicons:loading-03' : 'hugeicons:image-download-02'"
                    :class="downloading && 'animate-spin'"
                  />
                  {{ downloading ? "Rendering…" : "Download" }}
                </Button>
              </div>
              <p class="text-muted-foreground text-xs tracking-tight">High-res, ~4K (3840px wide).</p>
            </div>

            <div class="space-y-2 border-t pt-4">
              <p class="text-muted-foreground text-xs font-medium tracking-tight uppercase">Video</p>
              <div class="flex items-center gap-x-2">
                <Select v-model="videoSeconds">
                  <SelectTrigger size="sm" class="w-24"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="s in ['3', '5', '10', '15']" :key="s" :value="s">{{ s }}s</SelectItem>
                  </SelectContent>
                </Select>
                <Button size="sm" class="flex-1" :disabled="recording" @click="recordClip">
                  <Icon
                    :name="recording ? 'hugeicons:record' : 'hugeicons:video-01'"
                    :class="recording && 'animate-pulse'"
                  />
                  {{ recording ? `Recording ${Math.round(recordProgress * 100)}%` : "Record" }}
                </Button>
              </div>
              <p class="text-muted-foreground text-xs tracking-tight">MP4 (H.264) · ~1080p · 60fps.</p>
            </div>
          </PopoverContent>
        </Popover>
        <Button size="sm" @click="copyCode">
          <Icon :name="copied ? 'hugeicons:tick-02' : 'hugeicons:source-code'" />
          {{ copied ? "Copied" : "Copy code" }}
        </Button>
      </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
      <!-- Layers -->
      <aside class="flex w-60 shrink-0 flex-col border-r">
        <div class="flex items-center justify-between px-3 py-2">
          <span class="text-muted-foreground text-xs font-medium tracking-tight uppercase">Layers</span>
          <Popover v-model:open="pickerOpen">
            <PopoverTrigger as-child>
              <Button variant="ghost" size="iconSm" class="text-muted-foreground"><Icon name="hugeicons:add-01" /></Button>
            </PopoverTrigger>
            <PopoverContent align="end" class="w-60 p-0">
              <Command>
                <CommandInput placeholder="Add component…" />
                <CommandList class="max-h-72">
                  <CommandEmpty>No component.</CommandEmpty>
                  <CommandGroup v-for="group in grouped" :key="group.category" :heading="group.category">
                    <CommandItem
                      v-for="comp in group.components"
                      :key="comp.name"
                      :value="comp.name"
                      class="tracking-tight"
                      @select="addComponent(comp.name)"
                    >
                      {{ comp.name }}
                    </CommandItem>
                  </CommandGroup>
                </CommandList>
              </Command>
            </PopoverContent>
          </Popover>
        </div>
        <div class="flex-1 overflow-y-auto px-2 pb-3">
          <ShaderLayerItem
            :nodes="tree"
            :active-id="activeId"
            @select="activeId = $event"
            @remove="removeLayer"
            @move="moveLayer"
          />
          <p v-if="!tree.length" class="text-muted-foreground px-2 py-6 text-center text-xs tracking-tight">
            No layers. Add a component to begin.
          </p>
        </div>
      </aside>

      <!-- Canvas -->
      <main class="bg-muted/30 flex flex-1 items-center justify-center overflow-hidden p-4 sm:p-8">
        <div class="ring-border aspect-video w-full max-w-4xl overflow-hidden rounded-xl ring-1">
          <ShaderCanvas
            ref="canvasRef"
            :config="config"
            :color-space="colorSpace"
            :tone-mapping="toneMapping"
            class="size-full"
          />
        </div>
      </main>

      <!-- Controls -->
      <aside class="flex w-80 shrink-0 flex-col border-l">
        <div class="border-b px-4 py-2.5">
          <p class="text-sm font-medium tracking-tight">{{ activeNode?.type ?? "No selection" }}</p>
          <p class="text-muted-foreground text-xs tracking-tight">Properties</p>
        </div>
        <div class="flex-1 overflow-y-auto px-4 py-3">
          <ShaderControls v-if="activeNode" :key="activeNode.id" :node="activeNode" />
          <p v-else class="text-muted-foreground py-8 text-center text-xs tracking-tight">
            Select a layer to edit its properties.
          </p>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watchEffect, onMounted } from "vue";
import { useShaderPresets } from "@/components/shaders-docs/useShaderPresets";
import CodeBlock from "@/components/ui-docs/CodeBlock.vue";
import ShaderCanvas from "@/components/shaders/ShaderCanvas.vue";
import ShaderControls from "@/components/shaders/ShaderControls.vue";
import ShaderLayerProps from "@/components/shaders/ShaderLayerProps.vue";
import ShaderLayerItem from "@/components/shaders/ShaderLayerItem.vue";
import ShaderComponentBar from "@/components/shaders/ShaderComponentBar.vue";
import FrameworkLogo from "@/components/shaders/FrameworkLogo.vue";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

definePageMeta({ layout: "empty" });
usePageMeta(null, { title: "Editor · Shaders" });

const route = useRoute();
const { getPreset } = useShaderPresets();
const { grouped } = useShaderRegistry();
const { downloadImage, recordVideo } = useShaderExport();

const COLOR_SPACES = ["p3-linear", "srgb"];
const TONE_MAPPINGS = ["linear", "reinhard", "cineon", "aces", "agx", "neutral", "hable", "unreal"];

const COLOR_SPACE_LABELS = { "p3-linear": "P3 Linear", srgb: "sRGB" };
const TONE_LABELS = {
  linear: "Linear",
  reinhard: "Reinhard",
  cineon: "Cineon",
  aces: "ACES",
  agx: "AgX",
  neutral: "Neutral",
  hable: "Hable",
  unreal: "Unreal",
};
const colorSpaceLabel = (v) => COLOR_SPACE_LABELS[v] ?? v;
const toneLabel = (v) => TONE_LABELS[v] ?? v;

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

const flatLayers = computed(() => {
  const totals = {};
  const countType = (nodes) => {
    for (const node of nodes) {
      totals[node.type] = (totals[node.type] ?? 0) + 1;
      if (node.children?.length) countType(node.children);
    }
  };
  countType(tree.value);

  const out = [];
  const running = {};
  const walk = (nodes) => {
    for (const node of nodes) {
      running[node.type] = (running[node.type] ?? 0) + 1;
      const label = totals[node.type] > 1 ? `${node.type} ${running[node.type]}` : node.type;
      out.push({ id: node.id, type: node.type, label });
      if (node.children?.length) walk(node.children);
    }
  };
  walk(tree.value);
  return out;
});

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

function duplicateLayer(id) {
  const ctx = findContext(tree.value, id);
  if (!ctx) return;
  const clone = JSON.parse(JSON.stringify(ctx.array[ctx.index]));
  assignIds([clone]);
  ctx.array.splice(ctx.index + 1, 0, clone);
  activeId.value = clone.id;
}

function addComponent(name) {
  const node = { id: newId(), type: name, props: {} };
  tree.value.push(node);
  activeId.value = node.id;
}

const { frameworks, generate } = useShaderCodegen();
const framework = ref("vue");
const exportedCode = ref("");
const currentLanguage = computed(
  () => frameworks.find((f) => f.value === framework.value)?.language ?? "vue",
);

const codeOpen = ref(false);
function selectFramework(value) {
  framework.value = value;
  codeOpen.value = true;
}

watchEffect(async () => {
  // Deep-clone the tree so this effect tracks nested prop edits. A plain
  // `computed(() => ({ components: tree.value }))` only re-runs when the array
  // identity changes, not when a prop deep inside a node mutates - serializing
  // here walks every node so the effect re-fires on any edit and the generator
  // receives plain objects.
  const snapshot = { components: JSON.parse(JSON.stringify(tree.value)) };
  const fw = framework.value;
  const cs = colorSpace.value;
  const tm = toneMapping.value;
  exportedCode.value = await generate(snapshot, { framework: fw, colorSpace: cs, toneMapping: tm });
});

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
  <div class="bg-background flex h-svh overflow-hidden">
    <!-- Sidebar: identity + layers + export -->
    <aside class="bg-card flex w-64 shrink-0 flex-col border-r">
      <div class="flex h-14 shrink-0 items-center gap-x-2 border-b px-3">
        <Button to="/shaders" variant="ghost" size="iconSm" class="text-muted-foreground -ml-1">
          <Icon name="hugeicons:arrow-left-01" />
        </Button>
        <div class="min-w-0">
          <p class="truncate text-sm font-medium tracking-tight">{{ title }}</p>
          <p class="text-muted-foreground text-xs tracking-tight">Shader editor</p>
        </div>
      </div>

      <div class="flex items-center justify-between px-3 py-2.5">
        <span class="text-muted-foreground text-xs font-medium tracking-tight uppercase">Layers</span>
        <span class="text-muted-foreground/60 text-xs tracking-tight">{{ flatLayers.length }}</span>
      </div>
      <div class="flex-1 overflow-y-auto px-2 pb-3">
        <ShaderLayerItem
          :nodes="tree"
          :active-id="activeId"
          @select="activeId = $event"
          @remove="removeLayer"
          @move="moveLayer"
          @duplicate="duplicateLayer"
        />
        <p
          v-if="!tree.length"
          class="text-muted-foreground px-2 py-6 text-center text-xs tracking-tight"
        >
          No layers yet. Add one from the toolbar.
        </p>
      </div>

      <!-- Export footer -->
      <div class="border-t p-2">
        <div class="flex items-center gap-x-1">
          <Popover v-model:open="codeOpen">
            <PopoverTrigger as-child>
              <Button variant="ghost" size="sm" class="gap-x-1.5 rounded-lg px-2">
                <Icon name="hugeicons:file-export" />
                Export
              </Button>
            </PopoverTrigger>
            <PopoverContent
              side="top"
              align="start"
              :side-offset="10"
              class="w-[min(88vw,34rem)] space-y-3 p-3"
            >
              <div class="flex items-center gap-x-1">
                <button
                  v-for="f in frameworks"
                  :key="f.value"
                  v-tippy="f.label"
                  class="ring-border hover:bg-muted flex size-8 items-center justify-center rounded-lg transition"
                  :class="framework === f.value ? 'bg-muted ring-1' : 'opacity-60 hover:opacity-100'"
                  @click="framework = f.value"
                >
                  <FrameworkLogo :name="f.value" class="size-4" />
                </button>
              </div>
              <div class="max-h-[55vh] overflow-y-auto">
                <CodeBlock :code="exportedCode" :language="currentLanguage" />
              </div>

              <div class="border-border/60 grid grid-cols-2 gap-2 border-t pt-3">
                <div class="flex items-center gap-x-1.5">
                  <Select v-model="imageFormat">
                    <SelectTrigger size="sm" class="w-20"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="jpeg">JPG</SelectItem>
                      <SelectItem value="png">PNG</SelectItem>
                      <SelectItem value="webp">WebP</SelectItem>
                    </SelectContent>
                  </Select>
                  <Button
                    size="sm"
                    variant="outline"
                    class="flex-1"
                    :disabled="downloading"
                    @click="downloadImg"
                  >
                    <Icon
                      :name="downloading ? 'hugeicons:loading-03' : 'hugeicons:download-01'"
                      :class="downloading && 'animate-spin'"
                    />
                    {{ downloading ? "…" : "Image" }}
                  </Button>
                </div>
                <div class="flex items-center gap-x-1.5">
                  <Select v-model="videoSeconds">
                    <SelectTrigger size="sm" class="w-20"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="s in ['3', '5', '10', '15']" :key="s" :value="s">{{ s }}s</SelectItem>
                    </SelectContent>
                  </Select>
                  <Button
                    size="sm"
                    variant="outline"
                    class="flex-1"
                    :disabled="recording"
                    @click="recordClip"
                  >
                    <Icon
                      :name="recording ? 'hugeicons:record' : 'hugeicons:video-01'"
                      :class="recording && 'animate-pulse'"
                    />
                    {{ recording ? `${Math.round(recordProgress * 100)}%` : "Video" }}
                  </Button>
                </div>
              </div>
              <p class="text-muted-foreground text-xs tracking-tight">
                Image is ~4K. Video is MP4 (H.264), ~1080p, 60fps.
              </p>
            </PopoverContent>
          </Popover>

          <div class="ml-auto flex items-center gap-x-0.5">
            <button
              v-for="f in frameworks"
              :key="f.value"
              v-tippy="f.label"
              class="hover:bg-muted flex size-7 items-center justify-center rounded-md transition"
              :class="framework === f.value ? 'bg-muted ring-border ring-1' : 'opacity-55 hover:opacity-100'"
              @click="selectFramework(f.value)"
            >
              <FrameworkLogo :name="f.value" class="size-4" />
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Canvas stage -->
    <main class="bg-muted/30 flex min-w-0 flex-1 flex-col">
      <!-- Top bar -->
      <div class="flex h-14 shrink-0 items-center justify-end gap-x-1.5 px-4">
        <Select v-model="colorSpace">
          <SelectTrigger size="sm" class="bg-card ring-border h-8 w-32 rounded-lg border-0 ring-1">
            <Icon name="hugeicons:color-picker" class="text-muted-foreground size-3.5" />
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="cs in COLOR_SPACES" :key="cs" :value="cs">{{ colorSpaceLabel(cs) }}</SelectItem>
          </SelectContent>
        </Select>
        <Select v-model="toneMapping">
          <SelectTrigger size="sm" class="bg-card ring-border h-8 w-28 rounded-lg border-0 ring-1">
            <Icon name="hugeicons:sun-03" class="text-muted-foreground size-3.5" />
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="tm in TONE_MAPPINGS" :key="tm" :value="tm">{{ toneLabel(tm) }}</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <!-- Contained canvas artboard -->
      <div class="relative min-h-0 flex-1 px-4 pb-4">
        <div class="ring-border size-full overflow-hidden rounded-xl ring-1">
          <ShaderCanvas
            ref="canvasRef"
            :config="config"
            :color-space="colorSpace"
            :tone-mapping="toneMapping"
            class="size-full"
          />
        </div>

        <!-- Component palette -->
        <div class="absolute bottom-8 left-1/2 z-30 -translate-x-1/2">
          <ShaderComponentBar :groups="grouped" @add="addComponent" />
        </div>
      </div>
    </main>

    <!-- Properties (docked) -->
    <aside class="bg-card flex w-80 shrink-0 flex-col border-l">
      <template v-if="activeNode">
        <div class="flex h-14 shrink-0 flex-col justify-center border-b px-4">
          <p class="text-sm font-medium tracking-tight">{{ activeNode.type }}</p>
          <p class="text-muted-foreground text-xs tracking-tight">Properties</p>
        </div>
        <div class="flex-1 overflow-y-auto px-4 py-3">
          <ShaderControls :key="activeNode.id" :node="activeNode" />
          <ShaderLayerProps
            :key="`layer-${activeNode.id}`"
            :node="activeNode"
            :layers="flatLayers"
          />
        </div>
      </template>
      <div v-else class="flex flex-1 items-center justify-center p-6">
        <p class="text-muted-foreground text-center text-sm tracking-tight">
          Select a layer to edit its properties.
        </p>
      </div>
    </aside>
  </div>
</template>

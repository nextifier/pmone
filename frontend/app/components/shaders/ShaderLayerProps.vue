<script setup>
import { computed } from "vue";
import { AccordionRoot } from "reka-ui";
import ShaderSection from "./ShaderSection.vue";
import { SliderRuler } from "@/components/ui/slider";
import { Switch } from "@/components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

/**
 * Universal layer-level controls every shader component accepts (opacity, blend
 * mode, visibility, and the 2D transform). These props live on `node.props`
 * alongside the component-specific props and are spread onto the live component
 * by ShaderTree, so edits render immediately and serialize into exported code.
 */
const props = defineProps({
  node: {
    type: Object,
    required: true,
  },
  // Flat list of every other layer ({ id, type }) that can be used as a mask
  // source. The masked component references the source by its id; ShaderTree
  // passes each node's id to its component so the renderer resolves it live.
  layers: {
    type: Array,
    default: () => [],
  },
});

const DEFAULT_TRANSFORM = {
  offsetX: 0,
  offsetY: 0,
  rotation: 0,
  scale: 1,
  anchorX: 0.5,
  anchorY: 0.5,
  edges: "transparent",
};

// keyof the package's blendModes map (core/blendModes).
const BLEND_MODES = [
  "normal",
  "normal-oklch",
  "normal-oklab",
  "multiply",
  "screen",
  "linearDodge",
  "overlay",
  "difference",
  "colorDodge",
  "exclusion",
  "color",
  "luminosity",
  "darken",
  "lighten",
  "colorBurn",
  "linearBurn",
  "softLight",
  "hardLight",
  "hue",
  "saturation",
];

const EDGES = ["transparent", "stretch", "mirror", "wrap"];

const TRANSFORM_FIELDS = [
  { key: "offsetX", label: "Offset X", min: -1, max: 1, step: 0.01 },
  { key: "offsetY", label: "Offset Y", min: -1, max: 1, step: 0.01 },
  { key: "rotation", label: "Rotation", min: -180, max: 180, step: 1 },
  { key: "scale", label: "Scale", min: 0, max: 3, step: 0.01 },
  { key: "anchorX", label: "Anchor X", min: 0, max: 1, step: 0.01 },
  { key: "anchorY", label: "Anchor Y", min: 0, max: 1, step: 0.01 },
];

const formatLabel = (s) =>
  s
    .replace(/-/g, " ")
    .replace(/([a-z])([A-Z])/g, "$1 $2")
    .replace(/\b\w/g, (m) => m.toUpperCase());

const MASK_TYPES = ["alpha", "alphaInverted", "luminance", "luminanceInverted"];
const NONE = "__none__";

const opacity = computed(() => props.node.props.opacity ?? 1);
const blendMode = computed(() => props.node.props.blendMode ?? "normal");
const visible = computed(() => props.node.props.visible ?? true);
const maskSource = computed(() => props.node.props.maskSource ?? NONE);
const maskType = computed(() => props.node.props.maskType ?? "alpha");

const maskOptions = computed(() => props.layers.filter((l) => l.id !== props.node.id));

function setProp(key, value) {
  props.node.props[key] = value;
}

function setMaskSource(value) {
  if (value === NONE) {
    delete props.node.props.maskSource;
  } else {
    props.node.props.maskSource = value;
  }
}

function transformVal(key) {
  return props.node.props.transform?.[key] ?? DEFAULT_TRANSFORM[key];
}

function setTransform(key, value) {
  props.node.props.transform = {
    ...DEFAULT_TRANSFORM,
    ...(props.node.props.transform ?? {}),
    [key]: value,
  };
}

function setTransformNumber(key, raw) {
  const n = Number.parseFloat(raw);
  if (!Number.isNaN(n)) setTransform(key, n);
}
</script>

<template>
  <AccordionRoot type="multiple" :default-value="['Layer', 'Mask', 'Transform']" class="w-full">
    <ShaderSection value="Layer" title="Layer">
        <!-- opacity -->
        <SliderRuler
          label="Opacity"
          :model-value="Number(opacity)"
          :min="0"
          :max="1"
          :step="0.01"
          @update:model-value="setProp('opacity', $event)"
        />

        <!-- blend mode -->
        <div class="space-y-1.5">
          <label class="text-muted-foreground text-sm tracking-tight">Blend mode</label>
          <Select :model-value="blendMode" @update:model-value="setProp('blendMode', $event)">
            <SelectTrigger class="h-9 w-full"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="mode in BLEND_MODES" :key="mode" :value="mode">
                {{ formatLabel(mode) }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- visibility -->
        <div class="flex items-center justify-between gap-x-2">
          <label class="text-muted-foreground text-sm tracking-tight">Visible</label>
          <Switch :model-value="visible" @update:model-value="setProp('visible', $event)" />
        </div>
    </ShaderSection>

    <ShaderSection value="Mask" title="Mask">
        <!-- mask source -->
        <div class="space-y-1.5">
          <label class="text-muted-foreground text-sm tracking-tight">Source layer</label>
          <Select :model-value="maskSource" @update:model-value="setMaskSource($event)">
            <SelectTrigger class="h-9 w-full"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem :value="NONE">None</SelectItem>
              <SelectItem v-for="layer in maskOptions" :key="layer.id" :value="layer.id">
                {{ layer.label ?? layer.type }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p class="text-muted-foreground text-xs tracking-tight">
            Use another layer's alpha or luminance to mask this one.
          </p>
        </div>

        <!-- mask type -->
        <div v-if="maskSource !== NONE" class="space-y-1.5">
          <label class="text-muted-foreground text-sm tracking-tight">Mask type</label>
          <Select :model-value="maskType" @update:model-value="setProp('maskType', $event)">
            <SelectTrigger class="h-9 w-full"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="type in MASK_TYPES" :key="type" :value="type">
                {{ formatLabel(type) }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
    </ShaderSection>

    <ShaderSection value="Transform" title="Transform">
        <SliderRuler
          v-for="field in TRANSFORM_FIELDS"
          :key="field.key"
          :label="field.label"
          :model-value="Number(transformVal(field.key))"
          :min="field.min"
          :max="field.max"
          :step="field.step"
          @update:model-value="setTransform(field.key, $event)"
        />

        <!-- edges -->
        <div class="space-y-1.5">
          <label class="text-muted-foreground text-sm tracking-tight">Edges</label>
          <Select
            :model-value="transformVal('edges')"
            @update:model-value="setTransform('edges', $event)"
          >
            <SelectTrigger class="h-9 w-full"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="edge in EDGES" :key="edge" :value="edge">
                {{ formatLabel(edge) }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
    </ShaderSection>
  </AccordionRoot>
</template>

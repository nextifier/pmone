<script setup>
import { computed } from "vue";
import ColorPicker from "./ColorPicker.vue";
import PositionPad from "./PositionPad.vue";
import ShaderDriverConfig from "./ShaderDriverConfig.vue";
import ShaderTextureInput from "./ShaderTextureInput.vue";
import ShaderShapeInput from "./ShaderShapeInput.vue";
import { AccordionRoot } from "reka-ui";
import ShaderSection from "./ShaderSection.vue";
import { Input } from "@/components/ui/input";
import { SliderRuler } from "@/components/ui/slider";
import { Switch } from "@/components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const props = defineProps({
  // The reactive ComponentConfig node being edited ({ type, props }).
  node: {
    type: Object,
    required: true,
  },
  // Other layers ({ id, label }), used as map-driver sources.
  layers: {
    type: Array,
    default: () => [],
  },
});

const { propsFor } = useShaderRegistry();

const schema = computed(() => propsFor(props.node.type));

const groups = computed(() => {
  const map = {};
  for (const [key, def] of Object.entries(schema.value ?? {})) {
    const group = def.ui?.group ?? "Other";
    (map[group] ??= []).push({ key, def });
  }
  return Object.entries(map).map(([name, items]) => ({ name, items }));
});

const openGroups = computed(() => groups.value.map((g) => g.name));

function controlType(def) {
  const t = def.ui?.type;
  return Array.isArray(t) ? t[0] : (t ?? "text");
}

function valueOf(key, def) {
  return props.node.props[key] ?? def.default;
}

function set(key, value) {
  props.node.props[key] = value;
}

function setNumber(key, raw) {
  const n = Number.parseFloat(raw);
  if (!Number.isNaN(n)) props.node.props[key] = n;
}

/** Range props flagged `["range","map"]` in the schema can be driven dynamically. */
function isDynamicCapable(def) {
  return Array.isArray(def.ui?.type) && def.ui.type.includes("map");
}

/** A driven prop holds a `{ type, ... }` object instead of a static number. */
function isDynamic(key) {
  const v = props.node.props[key];
  return v != null && typeof v === "object" && typeof v.type === "string";
}

function toggleDynamic(key, def) {
  if (isDynamic(key)) {
    props.node.props[key] = def.default ?? def.ui?.min ?? 0;
  } else {
    props.node.props[key] = {
      type: "auto-animate",
      mode: "ping-pong",
      outputMin: def.ui?.min ?? 0,
      outputMax: def.ui?.max ?? 1,
      speed: 1,
      easing: "sine",
    };
  }
}
</script>

<template>
  <AccordionRoot type="multiple" :default-value="openGroups" class="w-full">
    <ShaderSection
      v-for="group in groups"
      :key="group.name"
      :value="group.name"
      :title="group.name"
    >
      <div v-for="{ key, def } in group.items" :key="key" class="space-y-1.5">
          <div class="flex items-center justify-between gap-x-2">
            <label class="text-muted-foreground text-sm tracking-tight">
              {{ def.ui?.label ?? key }}
            </label>
            <button
              v-if="isDynamicCapable(def)"
              v-tippy="isDynamic(key) ? 'Dynamic driver on' : 'Drive this value dynamically'"
              class="flex size-6 shrink-0 items-center justify-center rounded-md transition"
              :class="
                isDynamic(key)
                  ? 'bg-primary text-primary-foreground'
                  : 'text-muted-foreground hover:bg-muted'
              "
              @click="toggleDynamic(key, def)"
            >
              <Icon name="hugeicons:flash" class="size-3.5" />
            </button>
          </div>

          <!-- color -->
          <ColorPicker
            v-if="controlType(def) === 'color'"
            :model-value="String(valueOf(key, def))"
            @update:model-value="set(key, $event)"
          />

          <!-- range: dynamic driver -->
          <ShaderDriverConfig
            v-else-if="controlType(def) === 'range' && isDynamic(key)"
            :model-value="valueOf(key, def)"
            :min="def.ui?.min ?? 0"
            :max="def.ui?.max ?? 100"
            :step="def.ui?.step ?? 1"
            :layers="layers"
            @update:model-value="set(key, $event)"
          />

          <!-- range: static -->
          <SliderRuler
            v-else-if="controlType(def) === 'range'"
            :model-value="Number(valueOf(key, def))"
            :min="def.ui?.min ?? 0"
            :max="def.ui?.max ?? 100"
            :step="def.ui?.step ?? 1"
            @update:model-value="set(key, $event)"
          />

          <!-- position -->
          <PositionPad
            v-else-if="controlType(def) === 'position'"
            :model-value="valueOf(key, def)"
            @update:model-value="set(key, $event)"
          />

          <!-- select -->
          <Select
            v-else-if="controlType(def) === 'select'"
            :model-value="valueOf(key, def)"
            @update:model-value="set(key, $event)"
          >
            <SelectTrigger class="h-9 w-full"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="opt in def.ui?.options ?? []" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </SelectItem>
            </SelectContent>
          </Select>

          <!-- texture source (image / video) -->
          <ShaderTextureInput
            v-else-if="controlType(def) === 'image-upload' || controlType(def) === 'video-upload'"
            :model-value="String(valueOf(key, def) ?? '')"
            :kind="controlType(def) === 'video-upload' ? 'video' : 'image'"
            @update:model-value="set(key, $event)"
          />

          <!-- custom shape (SVG/PNG → SDF upload) -->
          <ShaderShapeInput
            v-else-if="controlType(def) === 'shape-upload'"
            :model-value="String(valueOf(key, def) ?? '')"
            @update:model-value="set(key, $event)"
          />

          <!-- checkbox -->
          <Switch
            v-else-if="controlType(def) === 'checkbox'"
            :model-value="Boolean(valueOf(key, def))"
            @update:model-value="set(key, $event)"
          />

          <!-- text / fallback -->
          <Input
            v-else
            :model-value="valueOf(key, def)"
            class="text-xs sm:text-sm"
            @update:model-value="set(key, $event)"
          />

          <p v-if="def.description" class="text-muted-foreground text-xs tracking-tight">
            {{ def.description }}
          </p>
        </div>
    </ShaderSection>
  </AccordionRoot>
</template>

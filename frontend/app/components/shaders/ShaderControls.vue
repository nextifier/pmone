<script setup>
import { computed } from "vue";
import ColorPicker from "./ColorPicker.vue";
import PositionPad from "./PositionPad.vue";
import ShaderRangeControl from "./ShaderRangeControl.vue";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
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
</script>

<template>
  <Accordion type="multiple" :default-value="openGroups" class="w-full">
    <AccordionItem v-for="group in groups" :key="group.name" :value="group.name">
      <AccordionTrigger class="text-xs font-medium tracking-tight uppercase">
        {{ group.name }}
      </AccordionTrigger>
      <AccordionContent class="space-y-4 pt-1">
        <div v-for="{ key, def } in group.items" :key="key" class="space-y-1.5">
          <label class="text-muted-foreground block text-sm tracking-tight">
            {{ def.ui?.label ?? key }}
          </label>

          <!-- color -->
          <ColorPicker
            v-if="controlType(def) === 'color'"
            :model-value="String(valueOf(key, def))"
            @update:model-value="set(key, $event)"
          />

          <!-- range -->
          <ShaderRangeControl
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
      </AccordionContent>
    </AccordionItem>
  </Accordion>
</template>

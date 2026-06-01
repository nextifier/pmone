<script setup>
import { computed } from "vue";

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
});

const { propsFor } = useShaderRegistry();

function typeLabel(def) {
  const t = def.ui?.type;
  const base = Array.isArray(t) ? t[0] : t;
  if (base === "select" && def.ui?.options?.length) {
    return def.ui.options.map((o) => `"${o.value}"`).join(" | ");
  }
  return base ?? "-";
}

function formatDefault(value) {
  if (value === null || value === undefined) return "-";
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}

const rows = computed(() =>
  Object.entries(propsFor(props.name)).map(([key, def]) => ({
    key,
    type: typeLabel(def),
    default: formatDefault(def.default),
    description: def.description ?? "",
  })),
);
</script>

<template>
  <div class="overflow-x-auto rounded-xl border">
    <table class="w-full border-collapse text-left text-sm">
      <thead>
        <tr class="text-muted-foreground border-b">
          <th class="px-4 py-2.5 font-medium tracking-tight">Prop</th>
          <th class="px-4 py-2.5 font-medium tracking-tight">Type</th>
          <th class="px-4 py-2.5 font-medium tracking-tight">Default</th>
          <th class="px-4 py-2.5 font-medium tracking-tight">Description</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.key" class="border-b last:border-0 align-top">
          <td class="px-4 py-2.5 font-mono text-xs tracking-tight whitespace-nowrap sm:text-sm">
            {{ row.key }}
          </td>
          <td class="text-muted-foreground px-4 py-2.5 font-mono text-xs">{{ row.type }}</td>
          <td class="text-muted-foreground px-4 py-2.5 font-mono text-xs whitespace-nowrap">
            {{ row.default }}
          </td>
          <td class="text-muted-foreground px-4 py-2.5 text-sm tracking-tight">{{ row.description }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

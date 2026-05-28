<template>
  <div v-if="rows?.length">
    <p class="text-muted-foreground mb-2 text-sm tracking-tight">{{ label }}</p>
    <div class="overflow-hidden rounded-xl border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead
              v-for="col in columns"
              :key="col.key"
              :style="col.width ? { width: col.width } : undefined"
            >
              {{ col.label }}
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="row in rows" :key="row[columns[0].key]">
            <TableCell
              v-for="col in columns"
              :key="col.key"
              :class="cellClass(col)"
            >
              <span v-if="col.key === 'type'" class="text-muted-foreground">
                {{ row[col.key] }}
              </span>
              <template v-else>{{ row[col.key] }}</template>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  </div>
</template>

<script setup>
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

defineProps({
  label: {
    type: String,
    required: true,
  },
  columns: {
    type: Array,
    required: true,
  },
  rows: {
    type: Array,
    default: () => [],
  },
});

function cellClass(col) {
  if (col.mono) return "font-mono text-xs sm:text-sm";
  if (col.monoSmall) return "font-mono text-xs";
  return "text-sm tracking-tight";
}
</script>

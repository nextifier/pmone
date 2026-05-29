<script setup>
import { Kbd } from "@/components/ui/kbd";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

defineProps({
  keyboard: {
    type: Array,
    default: () => [],
  },
  notes: {
    type: Array,
    default: () => [],
  },
});
</script>

<template>
  <div class="space-y-4">
    <div v-if="keyboard?.length">
      <p class="text-muted-foreground mb-2 text-sm tracking-tight">Keyboard</p>
      <div class="overflow-hidden rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead style="width: 200px">Shortcut</TableHead>
              <TableHead>Description</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="binding in keyboard" :key="binding.keys.join('+')">
              <TableCell>
                <span class="flex flex-wrap items-center gap-1">
                  <template v-for="(key, i) in binding.keys" :key="key">
                    <Kbd>{{ key }}</Kbd>
                    <span v-if="i < binding.keys.length - 1" class="text-muted-foreground text-xs">+</span>
                  </template>
                </span>
              </TableCell>
              <TableCell class="text-sm tracking-tight">{{ binding.description }}</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>

    <ul
      v-if="notes?.length"
      class="text-muted-foreground list-disc space-y-1.5 pl-5 text-sm tracking-tight"
    >
      <li v-for="(note, i) in notes" :key="i">{{ note }}</li>
    </ul>
  </div>
</template>

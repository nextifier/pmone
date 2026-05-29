<script setup>
import { ref } from "vue";
import {
  Combobox,
  ComboboxAnchor,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxInput,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxList,
} from "@/components/ui/combobox";

const value = ref([]);
const fruits = [
  { label: "Apple", value: "apple" },
  { label: "Banana", value: "banana" },
  { label: "Blueberry", value: "blueberry" },
  { label: "Grapes", value: "grapes" },
  { label: "Pineapple", value: "pineapple" },
];
</script>

<template>
  <Combobox v-model="value" by="value" multiple class="w-full max-w-xs">
    <ComboboxAnchor>
      <ComboboxInput placeholder="Pick fruits" />
    </ComboboxAnchor>
    <ComboboxList>
      <ComboboxEmpty>No fruit found.</ComboboxEmpty>
      <ComboboxGroup>
        <ComboboxItem v-for="f in fruits" :key="f.value" :value="f">
          {{ f.label }}
          <ComboboxItemIndicator>
            <Icon name="lucide:check" class="ml-auto size-4" />
          </ComboboxItemIndicator>
        </ComboboxItem>
      </ComboboxGroup>
    </ComboboxList>
  </Combobox>
</template>

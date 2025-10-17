<template>
  <div class="space-y-2">
    <Label for="visibility">Profile Visibility</Label>
    <Select v-model="localValue">
      <SelectTrigger class="w-full">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value="public">Public</SelectItem>
        <SelectItem value="private">Private</SelectItem>
      </SelectContent>
    </Select>
    <InputErrorMessage :errors="errors" />
    <p v-if="showDescription" class="text-muted-foreground text-xs">
      Public profiles can be viewed by anyone. Private profiles are only visible to you.
    </p>
  </div>
</template>

<script setup>
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const props = defineProps({
  modelValue: {
    type: String,
    default: "public",
  },
  errors: {
    type: Array,
    default: null,
  },
  showDescription: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue"]);

const localValue = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});
</script>

<script setup lang="ts">
import {
  ComboboxAnchor,
  ComboboxGroup,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxViewport,
} from "@/components/ui/combobox";
import { LucideCheck, LucideX } from "lucide-vue-next";
import {
  ComboboxInput,
  ComboboxRoot,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
  TagsInputRoot,
  useFilter,
} from "reka-ui";
import { computed } from "vue";

interface Project {
  id: number;
  name: string;
  [key: string]: any;
}

interface ProjectMultiSelectProps {
  projects: Project[];
  placeholder?: string;
  hideClearAllButton?: boolean;
  openOnFocus?: boolean;
}

const query = defineModel<string>("query", {
  default: "",
});

const modelValue = defineModel<Project[]>("modelValue", {
  default: () => [],
});

const { projects, placeholder, openOnFocus } = defineProps<ProjectMultiSelectProps>();

const { contains } = useFilter({ sensitivity: "base" });

const filteredProjects = computed(() => {
  if (!projects || !Array.isArray(projects)) return [];

  return projects.filter(
    (project) =>
      contains(project.name || "", query.value) &&
      !modelValue.value.some((item) => item.id === project.id)
  );
});

watch(
  modelValue,
  () => {
    query.value = "";
  },
  { deep: true }
);

const removeTag = (index: number) => {
  modelValue.value = modelValue.value.filter((item, i) => i !== index);
};
</script>

<template>
  <ComboboxRoot v-model="modelValue" multiple ignore-filter :open-on-focus="openOnFocus">
    <ComboboxAnchor class="w-full">
      <TagsInputRoot
        v-model="modelValue"
        delimiter=""
        class="border-input focus-within:border-ring focus-within:ring-ring has-aria-invalid:ring-destructive/20 dark:has-aria-invalid:ring-destructive/40 has-aria-invalid:border-destructive relative min-h-9.5 cursor-text rounded-md border p-1 text-sm transition-[color,box-shadow] outline-none focus-within:ring-[1px] has-disabled:pointer-events-none has-disabled:cursor-not-allowed has-disabled:opacity-50"
        :class="{
          'pe-9': !hideClearAllButton,
        }"
      >
        <div class="flex flex-wrap gap-1">
          <TagsInputItem
            v-for="(project, index) in modelValue"
            :key="project.id"
            :value="project.name"
            class="animate-fadeIn bg-background text-secondary-foreground hover:bg-background relative inline-flex h-7 cursor-default items-center rounded-md border pr-7 pl-1 text-sm font-medium tracking-tight transition-all disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 data-fixed:pe-2"
          >
            <Avatar :model="project" size="sm" class="mr-1 size-5 shrink-0" rounded="rounded" />
            <TagsInputItemText class="text-xs tracking-tight" />
            <TagsInputItemDelete
              @click="() => removeTag(index)"
              class="text-muted-foreground/80 hover:text-foreground focus-visible:border-ring focus-visible:ring-ring absolute -inset-y-px -end-px flex size-7 items-center justify-center rounded-e-md border border-transparent p-0 outline-hidden transition-[color,box-shadow] outline-none focus-visible:ring-[1px]"
            >
              <LucideX class="size-3.5" aria-hidden="true" />
            </TagsInputItemDelete>
          </TagsInputItem>

          <ComboboxInput v-model="query" as-child>
            <TagsInputInput
              :placeholder="placeholder || 'Select projects'"
              class="placeholder:text-muted-foreground/70 flex-1 bg-transparent px-2 py-1 tracking-tight outline-hidden disabled:cursor-not-allowed"
              :class="{
                '-ml-1': modelValue.length !== 0,
              }"
              @keydown.enter.prevent
            />
          </ComboboxInput>
        </div>
        <button
          v-if="!hideClearAllButton && modelValue.length"
          type="button"
          class="text-muted-foreground/80 hover:text-foreground focus-visible:border-ring focus-visible:ring-ring absolute end-0 top-0 flex size-9 items-center justify-center rounded-md border border-transparent transition-[color,box-shadow] outline-none focus-visible:ring-[1px]"
          aria-label="Clear all"
          @click="() => (modelValue = [])"
        >
          <LucideX class="size-4" aria-hidden="true" />
        </button>
      </TagsInputRoot>
    </ComboboxAnchor>

    <ComboboxList class="max-h-[40vh] w-(--reka-combobox-trigger-width)">
      <ComboboxViewport>
        <ComboboxEmpty class="px-2 py-4">No results found.</ComboboxEmpty>

        <ComboboxGroup v-if="filteredProjects.length">
          <ComboboxItem v-for="project in filteredProjects" :key="project.id" :value="project">
            <div class="flex items-center gap-2">
              <Avatar :model="project" size="sm" class="size-5" rounded="rounded" />
              <span class="truncate tracking-tight">{{ project.name }}</span>
            </div>

            <ComboboxItemIndicator>
              <LucideCheck class="ml-auto size-4" />
            </ComboboxItemIndicator>
          </ComboboxItem>
        </ComboboxGroup>
      </ComboboxViewport>
    </ComboboxList>
  </ComboboxRoot>
</template>

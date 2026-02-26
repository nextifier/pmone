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

interface User {
  id: number;
  name: string;
  username: string;
  email: string;
  profile_image?: any;
  [key: string]: any;
}

interface UserMultiSelectProps {
  /** Available users to select from */
  users: User[];
  placeholder?: string;
  hideClearAllButton?: boolean;
}

const query = defineModel<string>("query", {
  default: "",
});

const modelValue = defineModel<User[]>("modelValue", {
  default: () => [],
});

const { users, placeholder } = defineProps<UserMultiSelectProps>();

const { contains } = useFilter({ sensitivity: "base" });

const filteredUsers = computed(() => {
  if (!users || !Array.isArray(users)) return [];

  return users.filter(
    (user) =>
      (contains(user.name || "", query.value) ||
        contains(user.email || "", query.value) ||
        contains(user.username || "", query.value)) &&
      !modelValue.value.some((item) => item.id === user.id)
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
  <ComboboxRoot v-model="modelValue" multiple ignore-filter>
    <ComboboxAnchor class="w-full">
      <TagsInputRoot
        v-model="modelValue"
        delimiter=""
        class="border-input focus-within:border-ring focus-within:ring-ring has-aria-invalid:ring-destructive/20 dark:has-aria-invalid:ring-destructive/40 has-aria-invalid:border-destructive relative min-h-[38px] cursor-text rounded-md border p-1 text-sm transition-[color,box-shadow] outline-none focus-within:ring-[1px] has-disabled:pointer-events-none has-disabled:cursor-not-allowed has-disabled:opacity-50"
        :class="{
          'pe-9': !hideClearAllButton,
        }"
      >
        <div class="flex flex-wrap gap-1">
          <TagsInputItem
            v-for="(user, index) in modelValue"
            :key="user.id"
            :value="user.name"
            class="animate-fadeIn bg-background text-secondary-foreground hover:bg-background relative inline-flex h-9 cursor-default items-center rounded-md border pr-9 pl-1 text-xs font-medium transition-all disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 data-fixed:pe-2"
          >
            <Avatar :model="user" class="mr-1 size-7 shrink-0 [&_span.initial]:!text-[10px]" />
            <TagsInputItemText class="text-xs tracking-tight" />
            <TagsInputItemDelete
              @click="() => removeTag(index)"
              class="text-muted-foreground/80 hover:text-foreground focus-visible:border-ring focus-visible:ring-ring absolute -inset-y-px -end-px flex size-9 items-center justify-center rounded-e-md border border-transparent p-0 outline-hidden transition-[color,box-shadow] outline-none focus-visible:ring-[1px]"
            >
              <LucideX class="size-3.5" aria-hidden="true" />
            </TagsInputItemDelete>
          </TagsInputItem>

          <ComboboxInput v-model="query" as-child>
            <TagsInputInput
              :placeholder="placeholder || 'Select users'"
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
          class="text-muted-foreground/80 hover:text-foreground focus-visible:border-ring focus-visible:ring-ring absolute end-0 top-1 flex size-9 items-center justify-center rounded-md border border-transparent transition-[color,box-shadow] outline-none focus-visible:ring-[1px]"
          aria-label="Clear all"
          @click="() => (modelValue = [])"
        >
          <LucideX class="size-4" aria-hidden="true" />
        </button>
      </TagsInputRoot>
    </ComboboxAnchor>

    <ComboboxList class="w-(--reka-combobox-trigger-width)">
      <ComboboxViewport>
        <ComboboxEmpty class="px-2 py-4">No results found.</ComboboxEmpty>

        <ComboboxGroup v-if="filteredUsers.length">
          <ComboboxItem v-for="user in filteredUsers" :key="user.id" :value="user">
            <UserProfile :user="user" />

            <ComboboxItemIndicator>
              <LucideCheck class="ml-auto size-4" />
            </ComboboxItemIndicator>
          </ComboboxItem>
        </ComboboxGroup>
      </ComboboxViewport>
    </ComboboxList>
  </ComboboxRoot>
</template>

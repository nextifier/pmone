<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <Label>Authors & Contributors</Label>
      <Button
        type="button"
        variant="outline"
        size="sm"
        @click="addAuthor"
      >
        <Icon name="lucide:plus" class="mr-2 h-4 w-4" />
        Add Author
      </Button>
    </div>

    <div v-if="localAuthors.length === 0" class="text-sm text-muted-foreground italic">
      No authors added. Add at least one primary author.
    </div>

    <div v-else class="space-y-3">
      <Card
        v-for="(author, index) in localAuthors"
        :key="index"
        class="p-4"
      >
        <div class="flex items-start gap-4">
          <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- User Selection -->
            <div class="space-y-2">
              <Label :for="`author-user-${index}`">User</Label>
              <Select v-model="author.user_id">
                <SelectTrigger :id="`author-user-${index}`">
                  <SelectValue placeholder="Select user" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="user in availableUsers"
                    :key="user.id"
                    :value="user.id"
                  >
                    {{ user.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Role Selection -->
            <div class="space-y-2">
              <Label :for="`author-role-${index}`">Role</Label>
              <Select v-model="author.role">
                <SelectTrigger :id="`author-role-${index}`">
                  <SelectValue placeholder="Select role" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="primary_author">Primary Author</SelectItem>
                  <SelectItem value="co_author">Co-Author</SelectItem>
                  <SelectItem value="contributor">Contributor</SelectItem>
                  <SelectItem value="editor">Editor</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <!-- Order -->
            <div class="space-y-2">
              <Label :for="`author-order-${index}`">Display Order</Label>
              <Input
                :id="`author-order-${index}`"
                v-model.number="author.order"
                type="number"
                min="0"
                placeholder="0"
              />
            </div>
          </div>

          <!-- Remove Button -->
          <Button
            type="button"
            variant="ghost"
            size="icon"
            @click="removeAuthor(index)"
            title="Remove Author"
          >
            <Icon name="lucide:x" class="h-4 w-4" />
          </Button>
        </div>

        <!-- Role Description -->
        <div v-if="author.role" class="mt-3 text-xs text-muted-foreground">
          {{ getRoleDescription(author.role) }}
        </div>
      </Card>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Card } from "@/components/ui/card";

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  availableUsers: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update:modelValue"]);

const localAuthors = ref([...props.modelValue]);

// Watch for external changes
watch(
  () => props.modelValue,
  (newValue) => {
    localAuthors.value = [...newValue];
  }
);

// Watch for internal changes and emit
watch(
  localAuthors,
  (newValue) => {
    emit("update:modelValue", newValue);
  },
  { deep: true }
);

function addAuthor() {
  localAuthors.value.push({
    user_id: null,
    role: "co_author",
    order: localAuthors.value.length,
  });
}

function removeAuthor(index) {
  localAuthors.value.splice(index, 1);
}

function getRoleDescription(role) {
  const descriptions = {
    primary_author: "Main author of the post, appears first in byline",
    co_author: "Contributed significantly to the post content",
    contributor: "Provided input or assistance with the post",
    editor: "Reviewed and edited the post content",
  };
  return descriptions[role] || "";
}
</script>

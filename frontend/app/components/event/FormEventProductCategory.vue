<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <!-- Title -->
    <div class="space-y-2">
      <Label for="title">Title <span class="text-destructive">*</span></Label>
      <Input id="title" v-model="form.title" placeholder="Category title" required />
      <p v-if="errors.title" class="text-destructive mt-1 text-xs">
        {{ Array.isArray(errors.title) ? errors.title[0] : errors.title }}
      </p>
    </div>

    <!-- Description -->
    <div class="space-y-2">
      <Label for="description">Description</Label>
      <TipTapEditor
        v-model="form.description"
        model-type="App\Models\EventProductCategory"
        collection="description_images"
        :sticky="false"
        min-height="120px"
        placeholder="Optional description for this category..."
      />
      <p v-if="errors.description" class="text-destructive mt-1 text-xs">
        {{ Array.isArray(errors.description) ? errors.description[0] : errors.description }}
      </p>
    </div>

    <!-- Submit -->
    <div class="flex justify-end pt-2">
      <Button type="submit" :disabled="submitting">
        <Icon v-if="submitting" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        {{ isEdit ? "Update Category" : "Create Category" }}
      </Button>
    </div>
  </form>
</template>

<script setup>
import TipTapEditor from "@/components/TipTapEditor.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "vue-sonner";

const props = defineProps({
  category: { type: Object, default: null },
  apiBase: { type: String, required: true },
});
const emit = defineEmits(["success"]);

const client = useSanctumClient();
const submitting = ref(false);
const errors = ref({});

const form = reactive({
  title: "",
  description: "",
});

watch(
  () => props.category,
  (newCategory) => {
    if (newCategory) {
      form.title = newCategory.title || "";
      form.description = newCategory.description || "";
    } else {
      form.title = "";
      form.description = "";
    }
  },
  { immediate: true }
);

const isEdit = computed(() => !!props.category);

async function handleSubmit() {
  submitting.value = true;
  errors.value = {};
  try {
    const url = isEdit.value ? `${props.apiBase}/${props.category.id}` : props.apiBase;
    const method = isEdit.value ? "PUT" : "POST";
    const body = {
      title: form.title,
      description: form.description || null,
    };

    await client(url, { method, body });
    toast.success(isEdit.value ? "Category updated" : "Category created");
    emit("success");
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response._data.errors || {};
    } else {
      toast.error(error.response?._data?.message || "Failed to save category");
    }
  } finally {
    submitting.value = false;
  }
}
</script>

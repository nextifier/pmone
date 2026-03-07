<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="24rem">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">{{ mode === "create" ? "Create Permission" : "Edit Permission" }}</h3>
        <p class="page-description">
          {{ mode === "create" ? "Add a new permission to the system." : "Update the permission name." }}
        </p>
      </div>

      <form @submit.prevent="handleSubmit" class="mt-4 space-y-4">
        <div class="space-y-2">
          <Label for="permission_name">Permission Name</Label>
          <Input
            id="permission_name"
            v-model="formData.name"
            placeholder="posts.publish"
            required
            auto-focus
          />
          <p v-if="errors.name" class="text-destructive text-xs">{{ errors.name[0] }}</p>
          <p class="text-muted-foreground text-xs tracking-tight">
            Use dot notation (e.g., "resource.action").
          </p>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="isOpen = false">Cancel</Button>
          <Button type="submit" :disabled="loading">
            <Spinner v-if="loading" />
            {{ mode === "create" ? "Create" : "Save" }}
            <KbdGroup>
              <Kbd>{{ metaSymbol }}</Kbd>
              <Kbd>S</Kbd>
            </KbdGroup>
          </Button>
        </div>
      </form>
    </div>
  </DialogResponsive>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import { toast } from "vue-sonner";

const props = defineProps({
  permission: { type: Object, default: null },
});

const emit = defineEmits(["success"]);
const isOpen = defineModel("open", { type: Boolean, default: false });

const sanctumFetch = useSanctumClient();
const { metaSymbol } = useShortcuts();

const mode = computed(() => (props.permission ? "edit" : "create"));
const formData = ref({ name: "" });
const errors = ref({});
const loading = ref(false);

watch(isOpen, (val) => {
  if (val) {
    formData.value.name = props.permission?.name || "";
    errors.value = {};
  }
});

async function handleSubmit() {
  loading.value = true;
  errors.value = {};

  try {
    const endpoint =
      mode.value === "create" ? "/api/permissions" : `/api/permissions/${props.permission.id}`;
    const method = mode.value === "create" ? "POST" : "PUT";

    await sanctumFetch(endpoint, { method, body: formData.value });

    toast.success(
      mode.value === "create" ? "Permission created successfully!" : "Permission updated successfully!"
    );
    isOpen.value = false;
    emit("success");
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      toast.error(err.response._data.errors[firstErrorField][0]);
    } else {
      toast.error(err.response?._data?.message || err.message || `Failed to ${mode.value} permission`);
    }
  } finally {
    loading.value = false;
  }
}

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      if (isOpen.value) handleSubmit();
    },
  },
});
</script>

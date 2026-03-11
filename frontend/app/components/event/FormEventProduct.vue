<template>
  <form @submit.prevent="handleSubmit" class="space-y-4">
    <!-- Category -->
    <div class="space-y-2">
      <Label for="category_id">Category <span class="text-destructive">*</span></Label>
      <Select v-model="form.category_id" required>
        <SelectTrigger id="category_id" class="w-full">
          <SelectValue placeholder="Select category" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="cat in categoryOptions" :key="cat.id" :value="String(cat.id)">
            {{ cat.title }}
          </SelectItem>
        </SelectContent>
      </Select>
      <p v-if="errors.category_id" class="text-destructive mt-1 text-xs">
        {{ Array.isArray(errors.category_id) ? errors.category_id[0] : errors.category_id }}
      </p>
    </div>

    <!-- Name -->
    <div class="space-y-2">
      <Label for="name">Name <span class="text-destructive">*</span></Label>
      <Input id="name" v-model="form.name" placeholder="Product name" required />
      <p v-if="errors.name" class="text-destructive mt-1 text-xs">
        {{ Array.isArray(errors.name) ? errors.name[0] : errors.name }}
      </p>
    </div>

    <!-- Description -->
    <div class="space-y-2">
      <Label for="description">Description</Label>
      <Textarea
        id="description"
        v-model="form.description"
        placeholder="Optional description"
        rows="3"
      />
      <p v-if="errors.description" class="text-destructive mt-1 text-xs">
        {{ Array.isArray(errors.description) ? errors.description[0] : errors.description }}
      </p>
    </div>

    <!-- Price & Unit -->
    <div class="grid grid-cols-2 gap-x-3">
      <div class="space-y-2">
        <Label for="price">Price <span class="text-destructive">*</span></Label>
        <InputGroup>
          <InputGroupAddon>
            <InputGroupText>Rp</InputGroupText>
          </InputGroupAddon>
          <InputNumber
            id="price"
            v-model="form.price"
            :min="0"
            :decimal="true"
            placeholder="0"
            data-slot="input-group-control"
            class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
          />
        </InputGroup>
        <p v-if="errors.price" class="text-destructive mt-1 text-xs">
          {{ Array.isArray(errors.price) ? errors.price[0] : errors.price }}
        </p>
      </div>

      <div class="space-y-2">
        <Label for="unit">Unit</Label>
        <Select v-model="form.unit">
          <SelectTrigger id="unit" class="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="unit">Unit</SelectItem>
            <SelectItem value="set">Set</SelectItem>
            <SelectItem value="meter">Meter</SelectItem>
            <SelectItem value="sqm">Sqm</SelectItem>
            <SelectItem value="pcs">Pcs</SelectItem>
            <SelectItem value="kWh">kWh</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.unit" class="text-destructive mt-1 text-xs">
          {{ Array.isArray(errors.unit) ? errors.unit[0] : errors.unit }}
        </p>
      </div>
    </div>

    <!-- Booth Types -->
    <div class="space-y-2">
      <Label>Booth Types</Label>
      <p class="text-muted-foreground text-xs">Leave unchecked to make available for all booth types.</p>
      <div class="space-y-2">
        <div
          v-for="option in boothTypeOptions"
          :key="option.value"
          class="flex items-center gap-x-2"
        >
          <Checkbox
            :id="`booth_type_${option.value}`"
            :model-value="form.booth_types.includes(option.value)"
            @update:model-value="toggleBoothType(option.value)"
          />
          <Label :for="`booth_type_${option.value}`" class="text-sm font-normal">
            {{ option.label }}
          </Label>
        </div>
      </div>
      <p v-if="errors.booth_types" class="text-destructive mt-1 text-xs">
        {{ Array.isArray(errors.booth_types) ? errors.booth_types[0] : errors.booth_types }}
      </p>
    </div>

    <!-- Product Image -->
    <div class="space-y-2">
      <Label>Product Image</Label>
      <InputFileImage
        ref="productImageInputRef"
        v-model="imageFiles.product_image"
        :initial-image="props.product?.product_image"
        v-model:delete-flag="deleteFlags.product_image"
        container-class="relative isolate aspect-square max-w-48"
      />
      <p v-if="errors.tmp_product_image" class="text-destructive mt-1 text-xs">
        {{ Array.isArray(errors.tmp_product_image) ? errors.tmp_product_image[0] : errors.tmp_product_image }}
      </p>
    </div>

    <!-- Is Active -->
    <div class="flex items-center gap-x-2">
      <Switch id="is_active" v-model="form.is_active" />
      <Label for="is_active" class="text-sm font-normal">Active</Label>
    </div>

    <!-- Submit -->
    <div class="flex justify-end pt-2">
      <Button type="submit" :disabled="submitting">
        <Icon v-if="submitting" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        {{ isEdit ? "Update Product" : "Create Product" }}
      </Button>
    </div>
  </form>
</template>

<script setup>
import InputNumber from "@/components/InputNumber.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { InputGroup, InputGroupAddon, InputGroupText } from "@/components/ui/input-group";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

const props = defineProps({
  product: { type: Object, default: null },
  eventId: { type: Number, default: null },
  apiBase: { type: String, required: true },
});
const emit = defineEmits(["success"]);

const client = useSanctumClient();
const submitting = ref(false);
const errors = ref({});
const categoryOptions = ref([]);

const deleteFlags = ref({
  product_image: false,
});

const imageFiles = ref({
  product_image: [],
});

const productImageInputRef = ref(null);

const FILE_STATUS = {
  PROCESSING: 3,
};

const form = reactive({
  category_id: "",
  name: "",
  description: "",
  price: "",
  unit: "unit",
  booth_types: [],
  is_active: true,
});

watch(
  () => props.product,
  (newProduct) => {
    if (newProduct) {
      Object.assign(form, {
        ...newProduct,
        category_id: newProduct.category_id ? String(newProduct.category_id) : "",
        booth_types: newProduct.booth_types || [],
      });
      imageFiles.value.product_image = [];
      deleteFlags.value.product_image = false;
    } else {
      Object.assign(form, {
        category_id: "",
        name: "",
        description: "",
        price: "",
        unit: "unit",
        booth_types: [],
        is_active: true,
      });
      imageFiles.value.product_image = [];
      deleteFlags.value.product_image = false;
    }
  },
  { immediate: true }
);

const isEdit = computed(() => !!props.product);

const boothTypeOptions = [
  { value: "raw_space", label: "Raw Space" },
  { value: "standard_shell_scheme", label: "Standard Shell Scheme" },
  { value: "enhanced_shell_scheme", label: "Enhanced Shell Scheme" },
  { value: "table_chair_only", label: "Table & Chair Only" },
];

function toggleBoothType(value) {
  const idx = form.booth_types.indexOf(value);
  if (idx >= 0) {
    form.booth_types.splice(idx, 1);
  } else {
    form.booth_types.push(value);
  }
}

async function fetchCategories() {
  try {
    const res = await client(`${props.apiBase}/categories`);
    categoryOptions.value = res.data || [];
  } catch {
    // non-critical
  }
}

async function handleSubmit() {
  if (productImageInputRef.value?.pond?.getFiles().some((file) => file.status === FILE_STATUS.PROCESSING)) {
    toast.error("Please wait until image is uploaded");
    return;
  }

  submitting.value = true;
  errors.value = {};
  try {
    const url = isEdit.value ? `${props.apiBase}/${props.product.id}` : props.apiBase;
    const method = isEdit.value ? "PUT" : "POST";
    const body = {
      ...form,
      category_id: form.category_id ? Number(form.category_id) : null,
      booth_types: form.booth_types.length > 0 ? form.booth_types : null,
    };

    // Handle product image
    const imageValue = imageFiles.value.product_image?.[0];
    if (imageValue && typeof imageValue === "string" && imageValue.startsWith("tmp-")) {
        body.tmp_product_image = imageValue;
    } else if (deleteFlags.value.product_image && !imageValue) {
        body.delete_product_image = true;
    }

    await client(url, { method, body });
    toast.success(isEdit.value ? "Product updated" : "Product created");
    emit("success");
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response._data.errors || {};
    } else {
      toast.error(error.response?._data?.message || "Failed to save product");
    }
  } finally {
    submitting.value = false;
  }
}

onMounted(fetchCategories);
</script>

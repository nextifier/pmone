<script setup>
import { computed, nextTick, watch } from "vue";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { LocationCombobox } from "@/components/ui/location-combobox";
import { Textarea } from "@/components/ui/textarea";
import countries from "@/data/countries.json";
import indonesiaCities from "@/data/indonesia-cities.json";
import indonesiaProvinces from "@/data/indonesia-provinces.json";

/**
 * Cascading address fields for the canonical `{street, city, province, country}`
 * JSONB shape used by contacts, hotels, and brands.
 *
 * The parent stays responsible for coercing an all-empty address to `null`
 * before submitting, and must replace the whole object (not individual keys)
 * when syncing from a loaded record.
 */
const model = defineModel({
  default: () => ({ street: "", city: "", province: "", country: "" }),
});

const props = defineProps({
  errors: { type: Object, default: () => ({}) },
});

const errorList = (key) => {
  const value = props.errors?.[key];
  if (value == null) return [];
  return Array.isArray(value) ? value : [value];
};

const isIndonesia = computed(() => model.value.country === "Indonesia");

const provinceOptions = computed(() => indonesiaProvinces);

const cityOptions = computed(() => {
  const province = indonesiaProvinces.find((p) => p.label === model.value.province);
  if (!province) return [];
  return indonesiaCities.filter((c) => c.province === province.value);
});

// Registered before the field watchers so it runs first within a flush: when the
// parent swaps in a whole new address object (edit mode), the cascade below must
// not wipe the province and city it just loaded.
let skipCascade = false;
watch(
  () => model.value,
  () => {
    skipCascade = true;
    nextTick(() => {
      skipCascade = false;
    });
  }
);

watch(
  () => model.value.country,
  (_, oldVal) => {
    if (skipCascade) return;
    if (oldVal) {
      model.value.province = "";
      model.value.city = "";
    }
  }
);

watch(
  () => model.value.province,
  (_, oldVal) => {
    if (skipCascade) return;
    if (oldVal) {
      model.value.city = "";
    }
  }
);
</script>

<template>
  <div class="grid grid-cols-1 gap-y-6">
    <Field :data-invalid="!!errorList('address.country').length">
      <FieldLabel>Country</FieldLabel>
      <LocationCombobox
        v-model="model.country"
        :options="countries"
        :pinned="['Indonesia']"
        show-flag
        placeholder="Select country"
      />
      <FieldError :errors="errorList('address.country')" />
    </Field>

    <div v-if="isIndonesia" class="grid grid-cols-1 gap-x-2 gap-y-6 sm:grid-cols-2">
      <Field :data-invalid="!!errorList('address.province').length">
        <FieldLabel>Province</FieldLabel>
        <LocationCombobox
          v-model="model.province"
          :options="provinceOptions"
          :pinned="['DKI Jakarta']"
          placeholder="Select province"
        />
        <FieldError :errors="errorList('address.province')" />
      </Field>
      <Field :data-invalid="!!errorList('address.city').length">
        <FieldLabel>City</FieldLabel>
        <LocationCombobox
          v-model="model.city"
          :options="cityOptions"
          :disabled="!model.province"
          placeholder="Select city"
        />
        <FieldError :errors="errorList('address.city')" />
      </Field>
    </div>

    <Field :data-invalid="!!errorList('address.street').length">
      <FieldLabel for="address_street">Street Address</FieldLabel>
      <Textarea
        :aria-invalid="!!errorList('address.street').length"
        id="address_street"
        v-model="model.street"
        rows="2"
        placeholder="Street, building, area"
      />
      <FieldError :errors="errorList('address.street')" />
    </Field>
  </div>
</template>

<template>
  <PhoneInput
    :model-value="phoneValue"
    noUseBrowserLocale
    :country-code="activeCountry"
    :ignored-countries="['AC', 'TA']"
    class="flex"
    @update="handleResults"
    @update:country-code="handleCountryChange"
  >
    <template #selector="{ inputValue, updateInputValue, countries }">
      <Popover v-model:open="open">
        <PopoverTrigger as-child>
          <Button
            variant="outline"
            class="border-border flex h-9 gap-1 rounded-s-lg rounded-e-none border border-e-0 px-3"
          >
            <Flag :country="inputValue" />
            <ChevronsUpDown class="h-4 w-4 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent class="w-[300px] p-0">
          <Command>
            <CommandInput placeholder="Search country..." />
            <CommandEmpty>No country found.</CommandEmpty>
            <CommandList>
              <CommandGroup>
                <CommandItem
                  v-for="option in countries"
                  :key="option.iso2"
                  :value="option.name"
                  class="gap-2"
                  @select="
                    () => {
                      updateInputValue(option.iso2);
                      open = false;
                      focused = true;
                    }
                  "
                >
                  <Flag :country="option?.iso2" />
                  <span class="flex-1 text-sm">{{ option.name }}</span>
                  <span class="text-foreground/50 text-sm">{{ option.dialCode }}</span>
                </CommandItem>
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
    </template>

    <template #input="{ inputValue, updateInputValue, placeholder }">
      <Input
        ref="phoneInput"
        class="rounded-s-none! rounded-e-lg"
        type="text"
        :model-value="inputValue"
        @input="updateInputValue"
        :placeholder="placeholder"
      />
    </template>
  </PhoneInput>
</template>

<script lang="ts" setup>
import { useFocus } from "@vueuse/core";
import PhoneInput from "base-vue-phone-input";
import { ChevronsUpDown } from "lucide-vue-next";

const props = defineProps<{
  modelValue?: string;
}>();

const emit = defineEmits<{
  "update:modelValue": [value: string];
}>();

const { getCountryCode } = usePhoneCountry();

const open = ref(false);
const phoneInput = ref(null);
const { focused } = useFocus(phoneInput);

const phoneValue = ref(props.modelValue || "");
const activeCountry = ref<string>(
  (props.modelValue ? getCountryCode(props.modelValue) : null) || "ID"
);

let isInitializing = true;
let lastEmittedValue = props.modelValue || "";

onMounted(() => {
  nextTick(() => {
    isInitializing = false;
  });
});

const handleResults = (results: any) => {
  if (isInitializing) return;
  let value = "";
  if (results?.e164 && results?.isValid) {
    value = results.e164;
  } else if (results?.phoneNumber) {
    value = results.phoneNumber;
  }
  lastEmittedValue = value;
  emit("update:modelValue", value);
};

const handleCountryChange = (countryCode: string) => {
  activeCountry.value = countryCode;
};

watch(
  () => props.modelValue,
  (newVal) => {
    const incoming = newVal || "";
    if (incoming === lastEmittedValue) return;
    isInitializing = true;
    phoneValue.value = incoming;
    if (incoming) {
      const detected = getCountryCode(incoming);
      if (detected) activeCountry.value = detected;
    } else {
      activeCountry.value = "ID";
    }
    nextTick(() => {
      isInitializing = false;
    });
  }
);
</script>

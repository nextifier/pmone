<template>
  <PhoneInput
    noUseBrowserLocale
    fetchCountry
    country-code="ID"
    :ignored-countries="['AC']"
    class="flex"
  >
    <template #selector="{ inputValue, updateInputValue, countries }">
      <Popover v-model:open="open">
        <PopoverTrigger as-child>
          <Button
            variant="outline"
            class="border-border flex h-9 gap-1 rounded-s-lg rounded-e-none border border-e-0 px-3"
          >
            <FlagComponent :country="inputValue" />
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
                  <FlagComponent :country="option?.iso2" />
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
const open = ref(false);
const phoneInput = ref(null);
const { focused } = useFocus(phoneInput);
</script>

import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "combobox",
  title: "Combobox",
  description:
    "Searchable dropdown that filters items as you type. Built on reka-ui. Use for pickers with many options where a flat Select feels cumbersome.",
  installation: {
    importPath: "@/components/ui/combobox",
    imports: [
      "Combobox",
      "ComboboxAnchor",
      "ComboboxInput",
      "ComboboxTrigger",
      "ComboboxList",
      "ComboboxEmpty",
      "ComboboxGroup",
      "ComboboxItem",
      "ComboboxItemIndicator",
      "ComboboxSeparator",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Type to filter a static option list.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Combobox",
      props: [
        { name: "modelValue", type: "any", default: "—", description: "Selected value. Supports v-model." },
        { name: "open", type: "boolean", default: "—", description: "Dropdown open state. Supports v-model:open." },
        { name: "filterFunction", type: "(items, search) => items", default: "—", description: "Override default fuzzy filter." },
      ],
    },
  ],
});

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
    {
      id: "multiple",
      title: "Multiple",
      description: "Set multiple to select several items at once.",
      examples: ["multiple"],
      align: "center",
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "Pass disabled to block all interaction.",
      examples: ["disabled"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Combobox",
      props: [
        { name: "modelValue", type: "any", default: "—", description: "Selected value. Supports v-model." },
        { name: "open", type: "boolean", default: "—", description: "Dropdown open state. Supports v-model:open." },
        { name: "multiple", type: "boolean", default: "false", description: "Allow selecting several items." },
        { name: "filterFunction", type: "(items, search) => items", default: "—", description: "Override default fuzzy filter." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the selection changes. Enables v-model." },
        { name: "update:open", description: "Fires when the dropdown opens or closes." },
      ],
      slots: [
        { name: "default", description: "Anchor, list, and item sub-components." },
      ],
    },
    {
      component: "ComboboxAnchor / ComboboxInput",
      props: [
        { name: "class", type: "string", default: "—", description: "Anchor wraps the trigger; Input is the search field. Forwards to reka-ui." },
      ],
      events: [
        { name: "update:modelValue", description: "(ComboboxInput) Fires as the search text changes." },
      ],
    },
    {
      component: "ComboboxList",
      props: [
        { name: "class", type: "string", default: "—", description: "Floating results panel. Forwards positioning props to reka-ui ComboboxContent." },
      ],
    },
    {
      component: "ComboboxItem",
      props: [
        { name: "value", type: "any", default: "—", description: "Value committed when the item is selected." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable selection of this item." },
      ],
      events: [
        { name: "select", description: "Fires when the item is chosen." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["A"], description: "Typing in the input filters the options." },
      { keys: ["↑"], description: "Moves the highlight to the previous option." },
      { keys: ["↓"], description: "Moves the highlight to the next option." },
      { keys: ["Home"], description: "Jumps to the first option." },
      { keys: ["End"], description: "Jumps to the last option." },
      { keys: ["Enter"], description: "Selects the highlighted option." },
      { keys: ["Esc"], description: "Closes the dropdown." },
    ],
    notes: [
      "Built on reka-ui Combobox, pairing a textbox input with a role=listbox of options.",
      "The input exposes aria-expanded and aria-activedescendant tracks the highlighted option.",
    ],
  },
});

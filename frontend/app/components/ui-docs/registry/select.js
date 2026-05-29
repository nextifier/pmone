import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "select",
  title: "Select",
  description:
    "Single-value dropdown built on reka-ui. For pickers that need search across many options, use Combobox instead.",
  installation: {
    importPath: "@/components/ui/select",
    imports: [
      "Select",
      "SelectTrigger",
      "SelectValue",
      "SelectContent",
      "SelectGroup",
      "SelectLabel",
      "SelectItem",
      "SelectSeparator",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Trigger, value placeholder, then SelectItem rows inside Content.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-group",
      title: "With groups",
      description:
        "Group options with SelectGroup and SelectLabel. Labels are visual separators, not selectable.",
      examples: ["with-group"],
      align: "center",
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "The disabled attribute on the Select root disables the trigger.",
      examples: ["disabled"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Select",
      props: [
        {
          name: "modelValue",
          type: "string",
          default: "—",
          description: "Selected value. Supports v-model.",
        },
        {
          name: "defaultValue",
          type: "string",
          default: "—",
          description: "Starting value when v-model is not used.",
        },
        {
          name: "disabled",
          type: "boolean",
          default: "false",
          description: "Disable the entire select.",
        },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the value changes. Enables v-model." },
        { name: "update:open", description: "Fires when the dropdown opens or closes." },
      ],
    },
    {
      component: "SelectTrigger / SelectContent",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Use class to set trigger width (e.g. w-[220px]).",
        },
      ],
    },
    {
      component: "SelectItem",
      props: [
        { name: "value", type: "string", default: "—", description: "Value committed when selected." },
        { name: "disabled", type: "boolean", default: "false", description: "Block selection of this item." },
      ],
      events: [
        { name: "select", description: "Fires when the item is chosen." },
      ],
    },
    {
      component: "SelectGroup / SelectLabel / SelectValue / SelectSeparator",
      props: [
        { name: "placeholder", type: "string", default: "—", description: "(SelectValue) Text shown when nothing is selected. Group/Label organise items; Separator divides them." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Space"], description: "Opens the listbox when the trigger is focused." },
      { keys: ["Enter"], description: "Opens the listbox, or selects the highlighted option." },
      { keys: ["↑"], description: "Moves the highlight to the previous option." },
      { keys: ["↓"], description: "Moves the highlight to the next option." },
      { keys: ["Home"], description: "Moves the highlight to the first option." },
      { keys: ["End"], description: "Moves the highlight to the last option." },
      { keys: ["Esc"], description: "Closes the listbox and returns focus to the trigger." },
    ],
    notes: [
      "Follows the listbox pattern; the trigger exposes aria-expanded and the active option via aria-activedescendant.",
      "Type-ahead jumps to options whose label starts with the typed characters.",
      "Selected option is marked with aria-selected.",
    ],
  },
});

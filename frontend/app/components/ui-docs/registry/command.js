import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "command",
  title: "Command",
  description:
    "Command palette pattern. Composable input + grouped item list with fuzzy search. CommandDialog wraps it in a modal for global ⌘K menus.",
  installation: {
    importPath: "@/components/ui/command",
    imports: [
      "Command",
      "CommandDialog",
      "CommandInput",
      "CommandList",
      "CommandEmpty",
      "CommandGroup",
      "CommandItem",
      "CommandSeparator",
      "CommandShortcut",
    ],
  },
  anatomy: {
    tree: [
      { component: "Command", children: [
        { component: "CommandInput" },
        { component: "CommandList", children: [
          { component: "CommandEmpty" },
          { component: "CommandGroup", children: [
            { component: "CommandItem", children: [ { component: "CommandShortcut" } ] },
          ]},
          { component: "CommandSeparator" },
        ]},
      ]},
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Inline command palette anchored to its container.",
      examples: ["default"],
      align: "start",
    },
    {
      id: "dialog",
      title: "Dialog (⌘K)",
      description: "Open as a global modal triggered by a keyboard shortcut.",
      examples: ["dialog"],
      align: "center",
    },
    {
      id: "with-groups",
      title: "Multiple groups",
      description: "Several CommandGroups divided by CommandSeparator for clearer sections.",
      examples: ["with-groups"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "Command",
      props: [
        { name: "modelValue", type: "string", default: "—", description: "Selected item value. Supports v-model." },
        { name: "filter", type: "(value, search) => number", default: "fuzzy", description: "Custom match scorer." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the highlighted/selected value changes." },
      ],
    },
    {
      component: "CommandDialog",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Dialog open state. Supports v-model:open." },
      ],
      events: [
        { name: "update:open", description: "Fires when the dialog opens or closes." },
      ],
    },
    {
      component: "CommandInput",
      props: [
        { name: "modelValue", type: "string", default: "—", description: "Search query. Supports v-model." },
        { name: "placeholder", type: "string", default: "—", description: "Placeholder text for the search field." },
      ],
    },
    {
      component: "CommandItem",
      props: [
        { name: "value", type: "string", default: "—", description: "Value used for filtering and selection." },
        { name: "disabled", type: "boolean", default: "false", description: "Block selection of this item." },
      ],
      events: [
        { name: "select", description: "Fires when the item is chosen via click or Enter." },
      ],
    },
    {
      component: "CommandGroup / CommandEmpty",
      props: [
        { name: "heading", type: "string", default: "—", description: "(CommandGroup) Section heading. CommandEmpty renders when no item matches." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["A"], description: "Typing in the input filters the item list." },
      { keys: ["↑"], description: "Moves the highlight to the previous item." },
      { keys: ["↓"], description: "Moves the highlight to the next item." },
      { keys: ["Enter"], description: "Selects the highlighted item." },
      { keys: ["Esc"], description: "Clears the search, or closes the palette when shown in a dialog." },
    ],
    notes: [
      "Follows the combobox/listbox pattern: a textbox input controls a role=listbox of items.",
      "aria-activedescendant tracks the highlighted item while DOM focus stays on the input.",
    ],
  },
});

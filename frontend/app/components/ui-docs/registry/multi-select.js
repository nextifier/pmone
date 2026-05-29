import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "multi-select",
  title: "Multi Select",
  description:
    "Tag-style multiple-choice picker. Selected items render as removable chips inside the input. Supports search, fixed (un-removable) items, and disabled items.",
  installation: {
    importPath: "@/components/ui/multi-select",
    imports: ["MultiSelect"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Select multiple tags from a list.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-fixed",
      title: "With fixed",
      description: "Mark some options fixed so they cannot be removed.",
      examples: ["with-fixed"],
      align: "center",
    },
    {
      id: "open-on-focus",
      title: "Open on focus",
      description: "Pass openOnFocus to open the dropdown as soon as the input is focused.",
      examples: ["open-on-focus"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "MultiSelect",
      props: [
        { name: "modelValue", type: "Option[]", default: "[]", description: "Selected options. Supports v-model." },
        { name: "query", type: "string", default: '""', description: "Search text. Supports v-model:query." },
        { name: "options", type: "Option[]", default: "[]", description: "All available options. Each: { value, label, disabled?, fixed? }." },
        { name: "defaultOptions", type: "Option[]", default: "—", description: "Options pre-selected on mount." },
        { name: "placeholder", type: "string", default: '"Select"', description: "Placeholder text when nothing is selected." },
        { name: "hideClearAllButton", type: "boolean", default: "false", description: "Hide the clear-all control." },
        { name: "openOnFocus", type: "boolean", default: "false", description: "Open the dropdown when the input gains focus." },
        { name: "openOnClick", type: "boolean", default: "false", description: "Open the dropdown on click." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the selected options change. Enables v-model." },
        { name: "update:query", description: "Fires as the search text changes. Enables v-model:query." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["A"], description: "Typing in the input filters the options." },
      { keys: ["↑"], description: "Moves the highlight to the previous option." },
      { keys: ["↓"], description: "Moves the highlight to the next option." },
      { keys: ["Enter"], description: "Adds the highlighted option as a tag." },
      { keys: ["Backspace"], description: "Removes the last tag when the input is empty." },
      { keys: ["Esc"], description: "Closes the dropdown." },
    ],
    notes: [
      "Combines a tags-input with a role=listbox of options.",
      "Selected items render as removable chips that expose a remove control.",
    ],
  },
});

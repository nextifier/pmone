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
  ],
  apiReference: [
    {
      component: "Command",
      props: [
        { name: "modelValue", type: "string", default: "—", description: "Selected item value. Supports v-model." },
        { name: "filter", type: "(value, search) => number", default: "fuzzy", description: "Custom match scorer." },
      ],
    },
    {
      component: "CommandDialog",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Dialog open state. Supports v-model:open." },
      ],
    },
  ],
});

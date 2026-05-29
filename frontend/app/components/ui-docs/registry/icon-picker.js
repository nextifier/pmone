import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "icon-picker",
  title: "Icon Picker",
  description:
    "Searchable picker for choosing an icon from the project's icon sets (hugeicons + lucide). Stores the icon name as a string.",
  installation: {
    importPath: "@/components/ui/icon-picker",
    imports: ["IconPicker"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Click to open the picker dialog, then search and select.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-value",
      title: "With value",
      description: "Bind v-model to a preset icon name to start with a selection.",
      examples: ["with-value"],
      align: "center",
    },
    {
      id: "custom-prefix",
      title: "Custom prefix",
      description: "Restrict the search to a single icon set with the prefix prop.",
      examples: ["custom-prefix"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "IconPicker",
      props: [
        { name: "modelValue", type: "string | null", default: "null", description: "Selected icon name. Supports v-model." },
        { name: "prefix", type: "string", default: '"hugeicons,lucide"', description: "Comma-separated icon set prefixes to search." },
        { name: "placeholder", type: "string", default: '"Pick an icon"', description: "Trigger button label when no icon is selected." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable the trigger." },
        { name: "popular", type: "string[]", default: "—", description: "Icon names shown before any search query." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires with the chosen icon name (or null). Enables v-model." },
      ],
    },
  ],
});

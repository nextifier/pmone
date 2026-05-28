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
  ],
  apiReference: [
    {
      component: "IconPicker",
      props: [
        { name: "modelValue", type: "string", default: "—", description: "Selected icon name. Supports v-model." },
        { name: "placeholder", type: "string", default: '"Pick icon"', description: "Trigger button label when no icon is selected." },
      ],
    },
  ],
});

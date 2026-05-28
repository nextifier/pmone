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
  ],
  apiReference: [
    {
      component: "MultiSelect",
      props: [
        { name: "modelValue", type: "Option[]", default: "[]", description: "Selected options. Supports v-model." },
        { name: "options", type: "Option[]", default: "[]", description: "All available options. Each: { value, label, disabled?, fixed? }." },
        { name: "placeholder", type: "string", default: "—", description: "Placeholder text when nothing is selected." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable the picker." },
      ],
    },
  ],
});

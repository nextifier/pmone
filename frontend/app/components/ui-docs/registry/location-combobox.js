import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "location-combobox",
  title: "Location Combobox",
  description:
    "Combobox tuned for picking countries, cities, or any location-like option. Supports a pinned section at the top for recent or featured items.",
  installation: {
    importPath: "@/components/ui/location-combobox",
    imports: ["LocationCombobox"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Pass options as { value, label } pairs.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-pinned",
      title: "With pinned",
      description: "pinned values appear at the top of the list.",
      examples: ["with-pinned"],
      align: "center",
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "Pass disabled to block selection.",
      examples: ["disabled"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "LocationCombobox",
      props: [
        { name: "modelValue", type: "string", default: '""', description: "Selected label. Supports v-model." },
        { name: "options", type: "{ value, label }[]", default: "[]", description: "Available options." },
        { name: "pinned", type: "string[]", default: "[]", description: "Values to surface at the top." },
        { name: "placeholder", type: "string", default: "—", description: "Placeholder text." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable selection." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires with the selected value. Enables v-model." },
      ],
    },
  ],
});

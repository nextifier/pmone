import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "input-phone",
  title: "Input Phone",
  description:
    "International phone input with a searchable country selector. Auto-formats the number for the chosen country and emits a parsed E.164 string.",
  installation: {
    importPath: "@/components/ui/input-phone",
    imports: ["InputPhone"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Country selector + formatted number input.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "InputPhone",
      props: [
        { name: "modelValue", type: "string", default: '""', description: "E.164-formatted phone string. Supports v-model." },
        { name: "defaultCountry", type: "string", default: '"US"', description: "ISO country code for the initial selection." },
        { name: "required", type: "boolean", default: "false", description: "Mark the inner input required." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable the control." },
      ],
    },
  ],
});

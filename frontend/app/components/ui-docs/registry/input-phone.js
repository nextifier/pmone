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
    {
      id: "with-label",
      title: "With label",
      description: "Pair with a Label for a complete form field.",
      examples: ["with-label"],
      align: "center",
    },
    {
      id: "required",
      title: "Required",
      description: "Pass required to mark the inner input required.",
      examples: ["required"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "InputPhone",
      props: [
        { name: "modelValue", type: "string", default: '""', description: "E.164-formatted phone string. Supports v-model." },
        { name: "required", type: "boolean", default: "false", description: "Mark the inner input required." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires with the parsed phone string. Enables v-model." },
      ],
    },
  ],
});

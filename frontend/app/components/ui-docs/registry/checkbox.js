import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "checkbox",
  title: "Checkbox",
  description:
    "Binary input. Supports checked, unchecked, and indeterminate states via modelValue.",
  installation: {
    importPath: "@/components/ui/checkbox",
    imports: ["Checkbox"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Single checkbox bound with v-model.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-label",
      title: "With label",
      description: "Wire to a Label for accessible click targets.",
      examples: ["with-label"],
      align: "center",
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "The disabled state grays the box and blocks input.",
      examples: ["disabled"],
      align: "center",
    },
    {
      id: "indeterminate",
      title: "Indeterminate",
      description: "Set modelValue to 'indeterminate' for tri-state pickers.",
      examples: ["indeterminate"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Checkbox",
      props: [
        { name: "modelValue", type: "boolean | 'indeterminate'", default: "—", description: "Checked state. Supports v-model." },
        { name: "defaultValue", type: "boolean | 'indeterminate'", default: "false", description: "Initial state when v-model is not used." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable input." },
        { name: "id", type: "string", default: "—", description: "Match with Label for prop." },
      ],
    },
  ],
});

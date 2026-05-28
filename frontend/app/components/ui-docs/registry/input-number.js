import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "input-number",
  title: "Input Number",
  description:
    "Numeric input with increment/decrement buttons. Use for quantities and bounded numbers. For unstyled number entry, use Input type=\"number\".",
  installation: {
    importPath: "@/components/ui/input-number",
    imports: ["InputNumber"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Bound with v-model. Use min, max, step to constrain.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-bounds",
      title: "With bounds",
      description: "min, max, and step constraints.",
      examples: ["with-bounds"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "InputNumber",
      props: [
        { name: "modelValue", type: "number", default: "—", description: "Current value. Supports v-model." },
        { name: "min", type: "number", default: "—", description: "Minimum value." },
        { name: "max", type: "number", default: "—", description: "Maximum value." },
        { name: "step", type: "number", default: "1", description: "Increment / decrement step." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable the control." },
      ],
    },
  ],
});

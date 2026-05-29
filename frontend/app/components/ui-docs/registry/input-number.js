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
      description: "Bound with v-model. Use min, max, decimal to constrain.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-bounds",
      title: "With bounds",
      description: "min, max, and decimal constraints.",
      examples: ["with-bounds"],
      align: "center",
    },
    {
      id: "decimal",
      title: "Decimal",
      description: "Pass decimal to allow a fractional part.",
      examples: ["decimal"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "InputNumber",
      props: [
        { name: "modelValue", type: "number | string", default: "null", description: "Current value. Supports v-model. Displayed thousand-separated." },
        { name: "placeholder", type: "string", default: '""', description: "Placeholder text." },
        { name: "min", type: "number", default: "—", description: "Minimum value. Negative input is blocked unless min < 0." },
        { name: "max", type: "number", default: "—", description: "Maximum value." },
        { name: "decimal", type: "boolean", default: "false", description: "Allow a decimal part." },
        { name: "class", type: "string | object | array", default: '""', description: "Extra classes." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires with the parsed number. Enables v-model." },
      ],
    },
  ],
});

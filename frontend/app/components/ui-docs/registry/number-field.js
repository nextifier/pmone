import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "number-field",
  title: "Number Field",
  description:
    "Compose a numeric input from primitives: NumberField + NumberFieldContent + Increment/Decrement buttons + NumberFieldInput. Use when you need a different layout than the bundled InputNumber.",
  installation: {
    importPath: "@/components/ui/number-field",
    imports: [
      "NumberField",
      "NumberFieldContent",
      "NumberFieldDecrement",
      "NumberFieldIncrement",
      "NumberFieldInput",
    ],
  },
  whenToUse: {
    title: "When to use NumberField vs InputNumber",
    description:
      "InputNumber is a single-component shortcut. Reach for NumberField when you want a different layout (e.g. stacked increment/decrement buttons or icon-only triggers).",
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Inline +/- with the input in the middle.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "NumberField",
      props: [
        { name: "modelValue", type: "number", default: "—", description: "Current value. Supports v-model." },
        { name: "min", type: "number", default: "—", description: "Minimum value." },
        { name: "max", type: "number", default: "—", description: "Maximum value." },
        { name: "step", type: "number", default: "1", description: "Increment / decrement step." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable the field." },
      ],
    },
  ],
});

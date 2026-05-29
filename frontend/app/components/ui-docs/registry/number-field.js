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
    {
      id: "with-bounds",
      title: "With bounds",
      description: "Constrain the value with min, max, and step.",
      examples: ["with-bounds"],
      align: "center",
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "Pass disabled to block the input and step buttons.",
      examples: ["disabled"],
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
      events: [
        { name: "update:modelValue", description: "Fires with the new number. Enables v-model." },
      ],
    },
    {
      component: "NumberFieldInput",
      props: [
        { name: "class", type: "string", default: "—", description: "The editable number input. Forwards to reka-ui NumberFieldInput." },
      ],
    },
    {
      component: "NumberFieldIncrement / NumberFieldDecrement",
      props: [
        { name: "class", type: "string", default: "—", description: "Step buttons. Default slot overrides the +/- icon. Forwards to reka-ui." },
      ],
    },
    {
      component: "NumberFieldContent",
      props: [
        { name: "class", type: "string", default: "—", description: "Row wrapper that groups the input with the step buttons." },
      ],
    },
  ],
});

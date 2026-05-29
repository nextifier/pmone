import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "stepper",
  title: "Stepper",
  description:
    "Multi-step process visualisation with numbered indicators, titles, and connectors. Good for checkout flows and onboarding.",
  installation: {
    importPath: "@/components/ui/stepper",
    imports: ["Stepper", "StepperItem", "StepperIndicator", "StepperTitle", "StepperDescription", "StepperSeparator", "StepperTrigger"],
  },
  anatomy: {
    tree: [
      { component: "Stepper", children: [
        { component: "StepperItem", children: [
          { component: "StepperTrigger", children: [
            { component: "StepperIndicator" },
            { component: "StepperTitle" },
            { component: "StepperDescription" },
          ]},
          { component: "StepperSeparator" },
        ]},
      ]},
    ],
  },
  sections: [
    { id: "default", title: "Default", description: "Three-step horizontal stepper.", examples: ["default"], align: "start" },
  ],
  apiReference: [
    {
      component: "Stepper",
      props: [
        { name: "modelValue", type: "number", default: "1", description: "1-based active step. Supports v-model." },
        { name: "orientation", type: '"horizontal" | "vertical"', default: '"horizontal"', description: "Axis." },
        { name: "linear", type: "boolean", default: "true", description: "Block jumping past incomplete steps." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the active step changes. Enables v-model." },
      ],
      slots: [
        { name: "default", description: "Scoped slot exposing the current step state for the items." },
      ],
    },
    {
      component: "StepperItem",
      props: [
        { name: "step", type: "number", default: "—", description: "1-based step number this item represents." },
        { name: "disabled", type: "boolean", default: "false", description: "Block activating this step." },
      ],
    },
    {
      component: "StepperTrigger / StepperIndicator",
      props: [
        { name: "class", type: "string", default: "—", description: "Trigger makes a step clickable; Indicator renders the numbered/checked badge." },
      ],
    },
    {
      component: "StepperTitle / StepperDescription / StepperSeparator",
      props: [
        { name: "class", type: "string", default: "—", description: "Per-step label, helper text, and the connector line between steps." },
      ],
    },
  ],
});

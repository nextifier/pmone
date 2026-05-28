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
    },
    {
      component: "StepperItem",
      props: [
        { name: "step", type: "number", default: "—", description: "1-based step number this item represents." },
      ],
    },
  ],
});

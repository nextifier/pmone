import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "slider",
  title: "Slider",
  description: "Continuous or stepped range input for numeric values. Supports single or multi-thumb selection.",
  installation: { importPath: "@/components/ui/slider", imports: ["Slider"] },
  sections: [
    { id: "default", title: "Default", description: "Single thumb, 0-100.", examples: ["default"] },
    { id: "range", title: "Range", description: "Two thumbs for selecting a range.", examples: ["range"] },
    { id: "stepped", title: "Stepped", description: "Snap to discrete step values.", examples: ["stepped"] },
  ],
  apiReference: [
    {
      component: "Slider",
      props: [
        { name: "modelValue", type: "number[]", default: "[0]", description: "Array of thumb values. Supports v-model." },
        { name: "min", type: "number", default: "0", description: "Min value." },
        { name: "max", type: "number", default: "100", description: "Max value." },
        { name: "step", type: "number", default: "1", description: "Increment between values." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable input." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires continuously as a thumb moves. Enables v-model." },
        { name: "value-commit", description: "Fires once when the user releases a thumb." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["←"], description: "Decreases the value by one step." },
      { keys: ["↓"], description: "Decreases the value by one step." },
      { keys: ["→"], description: "Increases the value by one step." },
      { keys: ["↑"], description: "Increases the value by one step." },
      { keys: ["Home"], description: "Sets the value to the minimum." },
      { keys: ["End"], description: "Sets the value to the maximum." },
      { keys: ["Page Up"], description: "Increases the value by a large step." },
      { keys: ["Page Down"], description: "Decreases the value by a large step." },
    ],
    notes: [
      "Each thumb has role=slider with aria-valuemin, aria-valuemax, and aria-valuenow.",
      "Provide an accessible label so screen readers announce the slider's purpose.",
    ],
  },
});

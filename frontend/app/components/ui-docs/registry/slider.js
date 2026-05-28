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
    },
  ],
});

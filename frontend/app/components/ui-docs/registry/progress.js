import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "progress",
  title: "Progress",
  description:
    "Horizontal progress bar. Set modelValue between 0 and 100.",
  installation: {
    importPath: "@/components/ui/progress",
    imports: ["Progress"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Static progress at 60%.",
      examples: ["default"],
    },
    {
      id: "animated",
      title: "Animated",
      description: "Bind to a ref to animate as data loads.",
      examples: ["animated"],
    },
  ],
  apiReference: [
    {
      component: "Progress",
      props: [
        { name: "modelValue", type: "number", default: "0", description: "Progress percentage, 0-100. Supports v-model." },
        { name: "max", type: "number", default: "100", description: "Max value." },
        { name: "class", type: "string", default: "—", description: "Override height or radius." },
      ],
    },
  ],
});

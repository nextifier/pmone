import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "toggle",
  title: "Toggle",
  description: "Two-state pressable button. Use inside toolbars for formatting controls (bold, italic, align).",
  installation: { importPath: "@/components/ui/toggle", imports: ["Toggle"] },
  sections: [
    { id: "default", title: "Default", description: "Pressable two-state button.", examples: ["default"], align: "center" },
    { id: "variants", title: "Variants", description: "default and outline.", examples: ["variants"], align: "center" },
    { id: "sizes", title: "Sizes", description: "sm, default, lg.", examples: ["sizes"], align: "center" },
  ],
  apiReference: [
    {
      component: "Toggle",
      props: [
        { name: "modelValue", type: "boolean", default: "false", description: "Pressed state. Supports v-model." },
        { name: "defaultValue", type: "boolean", default: "false", description: "Initial state." },
        { name: "variant", type: '"default" | "outline"', default: '"default"', description: "Visual style." },
        { name: "size", type: '"sm" | "default" | "lg"', default: '"default"', description: "Size variant." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable input." },
      ],
    },
  ],
});

import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "color-mode-buttons",
  title: "Color Mode Buttons",
  description:
    "Three-way colour mode picker (light, dark, system) with thumbnail previews. Drop into a settings panel to let users pick a theme.",
  installation: {
    importPath: "@/components/ui/color-mode-buttons",
    imports: ["ColorModeButtons"],
  },
  whenToUse: {
    title: "When to use Buttons vs Toggle",
    description:
      "Use ColorModeButtons in a settings page where the user explicitly chooses light, dark, or system with previews. Use ColorModeToggle in the header for a quick one-click flip.",
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Renders three thumbnail-buttons; current mode shows a check.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "ColorModeButtons",
      props: [
        {
          name: "—",
          type: "—",
          default: "—",
          description: "No props. Reads and writes the Nuxt color-mode store directly.",
        },
      ],
    },
  ],
});

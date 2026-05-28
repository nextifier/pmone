import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "navigation-menu",
  title: "Navigation Menu",
  description:
    "Horizontal navigation with hover-triggered mega-menu panels. Best for marketing-site headers where each top-level item reveals a grid of categorised links.",
  installation: {
    importPath: "@/components/ui/navigation-menu",
    imports: [
      "NavigationMenu",
      "NavigationMenuList",
      "NavigationMenuItem",
      "NavigationMenuTrigger",
      "NavigationMenuContent",
      "NavigationMenuLink",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Two top-level items with content panels.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "NavigationMenu",
      props: [
        { name: "modelValue", type: "string", default: "—", description: "Open menu item value. Supports v-model." },
        { name: "delayDuration", type: "number", default: "200", description: "Hover-open delay in ms." },
      ],
    },
  ],
});

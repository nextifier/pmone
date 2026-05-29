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
    {
      id: "simple-link",
      title: "Simple links",
      description: "Top-level links with no dropdown panel, styled like triggers.",
      examples: ["simple-link"],
      align: "center",
    },
    {
      id: "with-grid",
      title: "Grid panel",
      description: "A content panel laid out as a small grid of titled links.",
      examples: ["with-grid"],
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
      events: [
        { name: "update:modelValue", description: "Fires when the active panel changes." },
      ],
    },
    {
      component: "NavigationMenuItem / NavigationMenuTrigger",
      props: [
        { name: "value", type: "string", default: "—", description: "(NavigationMenuItem) Identifier matched against modelValue. Trigger opens its panel." },
      ],
    },
    {
      component: "NavigationMenuContent",
      props: [
        { name: "class", type: "string", default: "—", description: "The mega-menu panel. Forwards interaction props to reka-ui NavigationMenuContent." },
      ],
    },
    {
      component: "NavigationMenuLink",
      props: [
        { name: "asChild", type: "boolean", default: "false", description: "Render into a child element such as NuxtLink." },
        { name: "active", type: "boolean", default: "false", description: "Mark the link as the current page." },
      ],
      events: [
        { name: "select", description: "Fires when the link is chosen; closes the menu." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Tab"], description: "Moves focus to the next trigger or link." },
      { keys: ["Shift", "Tab"], description: "Moves focus to the previous trigger or link." },
      { keys: ["Enter"], description: "Opens the panel for the focused trigger." },
      { keys: ["Space"], description: "Opens the panel for the focused trigger." },
      { keys: ["↓"], description: "Moves focus within an open content panel." },
      { keys: ["Esc"], description: "Closes the open panel." },
    ],
    notes: [
      "Panels open on hover or keyboard focus of their trigger.",
      "Links inside a panel are normal tab stops rather than a roving-tabindex menu.",
    ],
  },
});

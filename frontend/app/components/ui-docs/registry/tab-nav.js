import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "tab-nav",
  title: "Tab Nav",
  description:
    "Underline-style nav tabs that map to NuxtLink routes. Use for page-level navigation between related routes (Settings > Account / Notifications / Security).",
  installation: { importPath: "@/components/ui/tab-nav", imports: ["TabNav"] },
  whenToUse: {
    title: "When to use TabNav vs Tabs",
    description:
      "TabNav switches the URL — each tab is a real route. Tabs switches view inside a single page without changing the URL.",
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Pass a tabs array of route descriptors.",
      examples: ["default"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "TabNav",
      props: [
        {
          name: "tabs",
          type: "Tab[]",
          default: "—",
          description:
            "Required. Each tab: { to: string, label: string, exact?: boolean, activeFor?: string[] }.",
        },
        { name: "class", type: "string", default: "—", description: "Override layout." },
      ],
    },
  ],
});

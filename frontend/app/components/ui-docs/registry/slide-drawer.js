import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "slide-drawer",
  title: "Slide Drawer",
  description:
    "Side-sliding drawer styled like Sheet but with a fixed body, header, and footer layout. Use for detail panels that always need a sticky header and footer.",
  installation: {
    importPath: "@/components/ui/slide-drawer",
    imports: [
      "SlideDrawer",
      "SlideDrawerHeader",
      "SlideDrawerBody",
      "SlideDrawerFooter",
      "SlideDrawerTitle",
      "SlideDrawerDescription",
    ],
  },
  whenToUse: {
    title: "When to use SlideDrawer vs Sheet",
    description:
      "Sheet is the lightweight side panel. SlideDrawer adds a header/body/footer layout convention so headers and footers stay sticky as the body scrolls.",
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Header, scrollable body, sticky footer.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "SlideDrawer",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
        { name: "side", type: '"left" | "right"', default: '"right"', description: "Edge to slide in from." },
        { name: "maxWidth", type: "string", default: '"480px"', description: "Max width of the drawer." },
      ],
    },
  ],
});

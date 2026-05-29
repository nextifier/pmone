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
    {
      id: "no-handle",
      title: "Without handle",
      description: "Set show-handle to false to hide the drag grabber.",
      examples: ["no-handle"],
      align: "center",
    },
    {
      id: "with-form",
      title: "With form",
      description: "Labelled fields in the body with actions in the sticky footer.",
      examples: ["with-form"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "SlideDrawer",
      props: [
        { name: "open", type: "boolean", default: "false", description: "Open state. Supports v-model:open." },
        { name: "showHandle", type: "boolean", default: "true", description: "Show the drag grabber at the top of the drawer." },
      ],
      events: [
        { name: "update:open", description: "Fires when the drawer opens or closes. Enables v-model:open." },
      ],
    },
    {
      component: "SlideDrawerHeader / SlideDrawerBody / SlideDrawerFooter",
      props: [
        { name: "class", type: "string", default: "—", description: "Header and footer stay sticky; Body is the scrollable middle region." },
      ],
    },
    {
      component: "SlideDrawerTitle / SlideDrawerDescription",
      props: [
        { name: "class", type: "string", default: "—", description: "Typography for the drawer heading and supporting text." },
      ],
    },
  ],
});

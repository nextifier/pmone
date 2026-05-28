import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "scroll-to-top",
  title: "Scroll To Top",
  description: "Floating button that scrolls the page to the top. Appears after the user scrolls past a threshold.",
  installation: {
    importPath: "@/components/ui/scroll-to-top",
    imports: ["ScrollToTop"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Drop once at the page root. Becomes visible after 400px of scroll.",
      examples: ["default"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "ScrollToTop",
      props: [
        { name: "threshold", type: "number", default: "400", description: "Scroll Y in px before the button appears." },
      ],
    },
  ],
});

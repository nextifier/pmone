import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "input-group",
  title: "Input Group",
  description:
    "Wraps an input with addons (prefix/suffix icons, text labels, or buttons) into a single composite control. The seams between the parts are flattened so it looks like one element.",
  installation: {
    importPath: "@/components/ui/input-group",
    imports: ["InputGroup", "InputGroupAddon", "InputGroupButton", "InputGroupInput", "InputGroupText"],
  },
  sections: [
    {
      id: "with-icon",
      title: "With icon",
      description: "Leading or trailing icon addon.",
      examples: ["with-icon"],
      align: "center",
    },
    {
      id: "with-text",
      title: "With text",
      description: "Static text label as an addon (currency symbol, units).",
      examples: ["with-text"],
      align: "center",
    },
    {
      id: "with-button",
      title: "With button",
      description: "Trailing button for inline submit or copy actions.",
      examples: ["with-button"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "InputGroup",
      props: [
        { name: "class", type: "string", default: "—", description: "Wrapper that flattens the seams between the input and its addons." },
      ],
    },
    {
      component: "InputGroupAddon",
      props: [
        { name: "align", type: '"start" | "end"', default: '"start"', description: "Visual position relative to the input." },
      ],
    },
    {
      component: "InputGroupButton",
      props: [
        { name: "variant", type: "ButtonVariants[\"variant\"]", default: '"ghost"', description: "Same enum as Button." },
        { name: "size", type: "ButtonVariants[\"size\"]", default: '"sm"', description: "Same enum as Button." },
      ],
    },
    {
      component: "InputGroupInput / InputGroupTextarea",
      props: [
        { name: "class", type: "string", default: "—", description: "Borderless input/textarea variants that sit flush inside the group." },
      ],
    },
    {
      component: "InputGroupText",
      props: [
        { name: "class", type: "string", default: "—", description: "Static text addon (currency symbol, unit, prefix label)." },
      ],
    },
  ],
});

import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "button-group",
  title: "Button Group",
  description:
    "Joins a row or column of Buttons (or inputs) so adjacent corners and borders collapse cleanly. Use for segmented controls, attached input + button, or toolbars.",
  installation: {
    importPath: "@/components/ui/button-group",
    imports: ["ButtonGroup", "ButtonGroupSeparator", "ButtonGroupText"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Horizontal row of Buttons that share borders.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "vertical",
      title: "Vertical",
      description: "Stack buttons by setting orientation to vertical.",
      examples: ["vertical"],
      align: "center",
    },
    {
      id: "with-input",
      title: "With input",
      description: "Attach an Input and Button into one shape.",
      examples: ["with-input"],
      align: "center",
    },
    {
      id: "with-separator",
      title: "With separator",
      description: "Insert ButtonGroupSeparator to visually divide segments.",
      examples: ["with-separator"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "ButtonGroup",
      props: [
        {
          name: "orientation",
          type: '"horizontal" | "vertical"',
          default: '"horizontal"',
          description: "Axis of the group.",
        },
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Extra classes, merged with cn().",
        },
      ],
    },
    {
      component: "ButtonGroupSeparator / ButtonGroupText",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Visual separator and inline static text label inside a group.",
        },
      ],
    },
  ],
});

import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "rating",
  title: "Rating",
  description:
    "Star rating with fractional display, half-star input, hover preview, and keyboard support. Interactive by default; set readonly for display-only.",
  installation: { importPath: "@/components/ui/rating", imports: ["Rating"] },
  sections: [
    { id: "default", title: "Default", description: "Interactive 5-star rating with v-model.", examples: ["default"], align: "center" },
    { id: "fractional", title: "Fractional display", description: "Any decimal value renders a partially filled star.", examples: ["fractional"], align: "center" },
    { id: "half", title: "Half-star input", description: "Click or arrow to the left half of a star for a .5 value.", examples: ["half"], align: "center" },
    { id: "show-value", title: "Show value", description: "Display the numeric value beside the stars.", examples: ["show-value"], align: "center" },
    { id: "sizes", title: "Sizes", description: "sm, default, lg.", examples: ["sizes"], align: "center" },
    { id: "colors", title: "Colors", description: "yellow (default), primary, foreground.", examples: ["colors"], align: "center" },
    { id: "readonly", title: "Readonly", description: "Display-only, no hover or keyboard.", examples: ["readonly"], align: "center" },
    { id: "disabled", title: "Disabled", description: "Dimmed and non-interactive for forms.", examples: ["disabled"], align: "center" },
  ],
  apiReference: [
    {
      component: "Rating",
      props: [
        { name: "modelValue", type: "number", default: "0", description: "Current rating. Supports v-model. Fractional values render partial fills. Clamped to [0, max]." },
        { name: "max", type: "number", default: "5", description: "Number of stars." },
        { name: "size", type: '"sm" | "default" | "lg"', default: '"default"', description: "Star and gap size." },
        { name: "readonly", type: "boolean", default: "false", description: "Display-only. Disables hover, click and keyboard." },
        { name: "disabled", type: "boolean", default: "false", description: "Dimmed and non-interactive. Use in disabled forms." },
        { name: "showValue", type: "boolean", default: "false", description: "Render the numeric value next to the stars." },
        { name: "allowHalf", type: "boolean", default: "false", description: "Allow half-star selection via pointer position and a keyboard step of 0.5." },
        { name: "clearable", type: "boolean", default: "false", description: "Clicking the current value resets the rating to 0." },
        { name: "color", type: '"yellow" | "warning" | "primary" | "foreground"', default: '"yellow"', description: "Filled-star color. Default yellow matches the ReUI rating." },
        { name: "class", type: "string", default: "undefined", description: "Classes for the root container." },
        { name: "starClass", type: "string", default: "undefined", description: "Classes for each star wrapper." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires on click or keyboard change with the new rating. Enables v-model. With clearable, clicking the current value emits 0." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Tab"], description: "Moves focus to or from the rating (interactive only)." },
      { keys: ["ArrowRight"], description: "Increase rating by one step (0.5 with allowHalf, else 1)." },
      { keys: ["ArrowUp"], description: "Increase rating by one step." },
      { keys: ["ArrowLeft"], description: "Decrease rating by one step." },
      { keys: ["ArrowDown"], description: "Decrease rating by one step." },
      { keys: ["Home"], description: "Set rating to 0." },
      { keys: ["End"], description: "Set rating to max." },
    ],
    notes: [
      'When interactive, the root uses role="slider" with aria-valuemin, aria-valuemax, aria-valuenow and aria-valuetext (e.g. "3.5 of 5").',
      'When readonly, the root uses role="img" with an aria-label describing the value.',
      'Provide an aria-label (e.g. "Product rating") to name the control; it forwards to the root element.',
      "Stars are aria-hidden; the value is conveyed entirely through ARIA attributes.",
    ],
  },
});

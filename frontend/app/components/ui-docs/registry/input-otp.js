import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "input-otp",
  title: "Input OTP",
  description:
    "One-time password input with per-character slots. Used for 2FA, email verification, and sign-in codes. Built on vue-input-otp.",
  installation: {
    importPath: "@/components/ui/input-otp",
    imports: ["InputOTP", "InputOTPGroup", "InputOTPSlot", "InputOTPSeparator"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Six-digit OTP.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-separator",
      title: "With separator",
      description: "Split slots into groups for readability.",
      examples: ["with-separator"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "InputOTP",
      props: [
        { name: "modelValue", type: "string", default: "—", description: "Code value. Supports v-model." },
        { name: "maxlength", type: "number", default: "6", description: "Total number of slots." },
        { name: "pattern", type: "string", default: "—", description: "Regex pattern for allowed characters." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable input." },
      ],
      events: [
        { name: "complete", description: "Fires when all slots are filled with valid characters." },
      ],
    },
  ],
});

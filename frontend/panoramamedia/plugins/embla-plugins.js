import { defineNuxtPlugin } from "#app";
import { WheelGesturesPlugin } from "embla-carousel-wheel-gestures";

export default defineNuxtPlugin(() => {
  return {
    provide: {
      wheelGesturesPlugin: WheelGesturesPlugin,
    },
  };
});

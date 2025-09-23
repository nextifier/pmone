import { defineNuxtPlugin } from "#app";
import NumberFlow from "@number-flow/vue";

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.component("NumberFlow", NumberFlow);
});

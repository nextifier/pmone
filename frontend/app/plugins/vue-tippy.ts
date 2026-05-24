import VueTippy from "vue-tippy";
import type { TippyPluginOptions } from "vue-tippy";

// Animation CSS imports temporarily disabled — Vite's dev SSR emits these as
// duplicated <link rel="stylesheet"> + modulepreload tags (with and without
// the @fs/ path prefix), and one variant's MIME mismatches when the dev page
// is loaded behind a CDN tunnel, breaking hydration. Tooltip animations gracefully
// degrade to default browser transitions until we revisit.
// import "tippy.js/animations/scale.css";
// import "tippy.js/animations/shift-away.css";
// import "tippy.js/animations/shift-toward.css";
// import "tippy.js/animations/perspective.css";

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.use(VueTippy, {
    component: "Tippy",
    directive: "tippy",
    defaultProps: {
      animation: "shift-away",
      delay: [200, 100],
      trigger: "mouseenter",
      touch: ["hold", 500],
      arrow: false,
    },
  } satisfies TippyPluginOptions);
});

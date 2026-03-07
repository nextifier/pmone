// TEMPORARY: capture Vue SSR errors with full stack trace
// DELETE THIS FILE after debugging is complete
export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.config.errorHandler = (error: any, instance, info) => {
    // Enrich the error with stack info before Nuxt handles it
    if (error && !error._debugEnriched) {
      error._debugEnriched = true;
      const stack = error.stack || "no stack";
      const component = instance?.$options?.__name || instance?.type?.__name || "unknown";
      error.message = `[${component}] ${error.message}\n\nStack: ${stack}\nInfo: ${info}`;
    }
    // Re-throw so Nuxt's default handler still processes it
    throw error;
  };
});

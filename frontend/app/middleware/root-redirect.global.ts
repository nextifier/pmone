/**
 * pmone.id should land on the dashboard, not the marketing index page. The
 * dashboard gates auth itself (signed-out visitors go to /login). A routeRule
 * redirect on "/" is ignored while pages/index.vue exists, so we redirect here -
 * runs on both the SSR request and client-side navigation.
 */
export default defineNuxtRouteMiddleware((to) => {
  if (to.path === "/") {
    return navigateTo("/dashboard", { redirectCode: 302 });
  }
});

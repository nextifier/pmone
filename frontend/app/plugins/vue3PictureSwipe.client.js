import VuePictureSwipe from "vue3-picture-swipe";

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.component("vue-picture-swipe", VuePictureSwipe);
});

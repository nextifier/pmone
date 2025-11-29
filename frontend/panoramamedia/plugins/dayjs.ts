import dayjs from "dayjs";
import relativeTime from "dayjs/plugin/relativeTime";
import customParseFormat from "dayjs/plugin/customParseFormat";

dayjs.extend(relativeTime);
dayjs.extend(customParseFormat);

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.provide("dayjs", dayjs);
});

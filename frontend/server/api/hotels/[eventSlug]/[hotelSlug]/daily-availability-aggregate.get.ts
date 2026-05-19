export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const eventSlug = getRouterParam(event, "eventSlug");
  const hotelSlug = getRouterParam(event, "hotelSlug");
  const query = getQuery(event);

  const baseUrl = (config.public as any).apiUrl || "http://localhost:8000";
  const apiKey = (config as any).pmOneApiKey;

  return await $fetch(
    `${baseUrl}/api/public/events/${eventSlug}/hotels/${hotelSlug}/daily-availability-aggregate`,
    {
      headers: { "X-API-Key": apiKey },
      query,
    }
  );
});

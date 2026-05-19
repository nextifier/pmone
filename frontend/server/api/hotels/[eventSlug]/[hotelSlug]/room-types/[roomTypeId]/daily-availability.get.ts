export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const eventSlug = getRouterParam(event, "eventSlug");
  const hotelSlug = getRouterParam(event, "hotelSlug");
  const roomTypeId = getRouterParam(event, "roomTypeId");
  const query = getQuery(event);

  const baseUrl = (config.public as any).apiUrl || "http://localhost:8000";
  const apiKey = (config as any).pmOneApiKey;

  return await $fetch(
    `${baseUrl}/api/public/events/${eventSlug}/hotels/${hotelSlug}/room-types/${roomTypeId}/daily-availability`,
    {
      headers: { "X-API-Key": apiKey },
      query,
    }
  );
});

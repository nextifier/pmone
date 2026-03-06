<template>
  <ClientOnly>
    <span>{{ greeting }}, {{ firstName }}!</span>
    <template #fallback>Hey, {{ firstName }}!</template>
  </ClientOnly>
</template>

<script setup>
const { user } = useSanctumAuth();

const firstName = computed(() => {
  const name = user.value?.name || "there";
  return name.split(" ")[0];
});

// All greetings must work naturally with ", {Name}!" appended.
// No questions marks, no trailing punctuation.
const GREETINGS = {
  morning: [
    "Good morning",
    "Morning",
    "Hey, good morning",
    "Rise and shine",
    "Top of the morning",
    "Hello",
    "Hey there",
    "Hi there",
    "Welcome back",
    "Great to see you",
    "Happy new day",
    "Fresh start today",
    "Let's make today count",
    "Here we go",
    "Off to a good start",
  ],
  afternoon: [
    "Good afternoon",
    "Afternoon",
    "Hey there",
    "Hi there",
    "Hello",
    "Welcome back",
    "Good to see you",
    "Great to see you",
    "Hope your day's been good",
    "Let's keep the momentum going",
    "Happy afternoon",
    "Right on track",
    "Halfway through the day",
    "Nice to see you back",
    "Looking good today",
  ],
  evening: [
    "Good evening",
    "Evening",
    "Hey there",
    "Hi there",
    "Hello",
    "Welcome back",
    "Great to see you",
    "Good to see you",
    "Hope today was a good one",
    "Almost there",
    "Winding down nicely",
    "Way to finish strong",
    "Nice to see you back",
    "Well done today",
    "Heading into the home stretch",
  ],
  night: [
    "Good evening",
    "Evening",
    "Hey there",
    "Hello",
    "Welcome back",
    "Great to see you",
    "Pulling a late one",
    "Night owl mode activated",
    "Still going strong",
    "Dedication at its finest",
    "Respect the hustle",
    "Quiet hours, best hours",
    "Late night productivity",
    "No rest for the dedicated",
    "Moonlight shift",
  ],
};

const getTimePeriod = () => {
  const hour = new Date().getHours();
  if (hour >= 5 && hour < 12) return "morning";
  if (hour >= 12 && hour < 17) return "afternoon";
  if (hour >= 17 && hour < 21) return "evening";
  return "night";
};

const pick = (arr) => arr[Math.floor(Math.random() * arr.length)];
const greeting = pick(GREETINGS[getTimePeriod()]);
</script>

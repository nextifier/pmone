<script setup lang="ts">
import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from "@/components/appearance/showcase/_ui/avatar"
import { Badge } from "@/components/appearance/showcase/_ui/badge"
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"

const usernames = [
  "shadcn",
  "vercel",
  "nextjs",
  "tailwindlabs",
  "typescript-lang",
  "eslint",
  "prettier",
  "babel",
  "webpack",
  "rollup",
  "parcel",
  "vite",
  "react",
  "vue",
  "angular",
  "solid",
]
</script>

<template>
  <Card class="max-w-sm">
    <CardHeader>
      <CardTitle>
        Contributors <Badge variant="secondary">
          312
        </Badge>
      </CardTitle>
    </CardHeader>
    <CardContent>
      <div class="flex flex-wrap gap-2">
        <Avatar
          v-for="username in usernames"
          :key="username"
          class="grayscale"
        >
          <AvatarImage
            :src="`https://github.com/${username}.png`"
            :alt="username"
          />
          <AvatarFallback>{{ username.charAt(0) }}</AvatarFallback>
        </Avatar>
      </div>
    </CardContent>
    <CardFooter>
      <a href="#" class="text-sm underline underline-offset-4">
        + 810 contributors
      </a>
    </CardFooter>
  </Card>
</template>

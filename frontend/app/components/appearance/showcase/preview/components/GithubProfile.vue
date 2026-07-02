<script setup lang="ts">
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  Field,
  FieldDescription,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
</script>

<template>
  <Card class="mx-auto w-full max-w-md">
    <CardHeader>
      <CardTitle>Profile</CardTitle>
      <CardDescription>Manage your profile information.</CardDescription>
    </CardHeader>
    <CardContent>
      <form id="profile">
        <FieldGroup>
          <Field>
            <FieldLabel for="name">
              Name
            </FieldLabel>
            <Input id="name" placeholder="shadcn" />
            <FieldDescription>
              Your name may appear around GitHub where you contribute or are
              mentioned. You can remove it at any time.
            </FieldDescription>
          </Field>
          <Field>
            <FieldLabel for="email">
              Public Email
            </FieldLabel>
            <Select model-value="m@shadcn.com">
              <SelectTrigger id="email" class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="m@shadcn.com">
                  m@shadcn.com
                </SelectItem>
                <SelectItem value="m@gmail.com">
                  m@gmail.com
                </SelectItem>
              </SelectContent>
            </Select>
            <FieldDescription>
              You can manage verified email addresses in your
              <a href="#email-settings">email settings</a>.
            </FieldDescription>
          </Field>
          <Field>
            <FieldLabel for="bio">
              Bio
            </FieldLabel>
            <Textarea
              id="bio"
              placeholder="Tell us a little bit about yourself"
            />
            <FieldDescription>
              You can <span>@mention</span> other users and organizations to
              link to them.
            </FieldDescription>
          </Field>
        </FieldGroup>
      </form>
    </CardContent>
    <CardFooter>
      <Button form="profile">
        Save Profile
      </Button>
    </CardFooter>
  </Card>
</template>

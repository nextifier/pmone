<script setup lang="ts">
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardFooter } from "@/components/ui/card"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
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
  <Card>
    <CardContent>
      <form id="feedback-form">
        <FieldGroup>
          <Field>
            <FieldLabel for="topic">
              Topic
            </FieldLabel>
            <Select>
              <SelectTrigger id="topic" class="w-full">
                <SelectValue placeholder="Select a topic" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="ai">
                  AI
                </SelectItem>
                <SelectItem value="accounts-and-access-controls">
                  Accounts and Access Controls
                </SelectItem>
                <SelectItem value="billing">
                  Billing
                </SelectItem>
                <SelectItem value="cdn">
                  CDN (Firewall, Caching)
                </SelectItem>
                <SelectItem value="ci-cd">
                  CI/CD (Builds, Deployments, Environment Variables)
                </SelectItem>
                <SelectItem value="dashboard-interface">
                  Dashboard Interface (Navigation, UI Issues)
                </SelectItem>
                <SelectItem value="domains">
                  Domains
                </SelectItem>
                <SelectItem value="frameworks">
                  Frameworks
                </SelectItem>
                <SelectItem value="marketplace-and-integrations">
                  Marketplace and Integrations
                </SelectItem>
                <SelectItem value="observability">
                  Observability (Observability, Logs, Monitoring)
                </SelectItem>
                <SelectItem value="storage">
                  Storage
                </SelectItem>
              </SelectContent>
            </Select>
          </Field>
          <Field>
            <FieldLabel for="feedback">
              Feedback
            </FieldLabel>
            <Textarea
              id="feedback"
              placeholder="Your feedback helps us improve"
            />
          </Field>
        </FieldGroup>
      </form>
    </CardContent>
    <CardFooter>
      <Button type="submit" form="feedback-form">
        Submit
      </Button>
    </CardFooter>
  </Card>
</template>

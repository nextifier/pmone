<script setup lang="ts">
import SlideDrawer from '../components/ui/slide-drawer/SlideDrawer.vue'
import SlideDrawerHeader from '../components/ui/slide-drawer/SlideDrawerHeader.vue'
import SlideDrawerFooter from '../components/ui/slide-drawer/SlideDrawerFooter.vue'
import SlideDrawerTitle from '../components/ui/slide-drawer/SlideDrawerTitle.vue'
import SlideDrawerDescription from '../components/ui/slide-drawer/SlideDrawerDescription.vue'
import SlideDrawerBody from '../components/ui/slide-drawer/SlideDrawerBody.vue'

definePageMeta({
  ssr: false,
})

// Basic drawer
const basicDrawerOpen = ref(false)

function openBasicDrawer() {
  console.log('openBasicDrawer called, current value:', basicDrawerOpen.value)
  basicDrawerOpen.value = true
  console.log('basicDrawerOpen is now:', basicDrawerOpen.value)
}

// Drawer with header and footer
const headerFooterDrawerOpen = ref(false)

// Scrollable content drawer
const scrollableDrawerOpen = ref(false)

// Form drawer
const formDrawerOpen = ref(false)
const formData = ref({
  name: '',
  email: '',
  message: '',
})

function handleFormSubmit() {
  console.log('Form submitted:', formData.value)
  formDrawerOpen.value = false
  formData.value = { name: '', email: '', message: '' }
}

// Confirmation drawer
const confirmDrawerOpen = ref(false)

function handleConfirm() {
  console.log('Confirmed!')
  confirmDrawerOpen.value = false
}

// Nested drawer
const nestedDrawerOpen = ref(false)
const innerNestedDrawerOpen = ref(false)

// No handle drawer
const noHandleDrawerOpen = ref(false)

// Profile drawer
const profileDrawerOpen = ref(false)

// Settings drawer
const settingsDrawerOpen = ref(false)
const notifications = ref(true)
const darkMode = ref(false)
const language = ref('id')
</script>

<template>
  <div class="container py-8">
    <div class="mb-8">
      <h1 class="text-3xl font-bold tracking-tight">SlideDrawer Component</h1>
      <p class="text-muted-foreground mt-2">
        A mobile-friendly bottom sheet drawer with swipe-to-close functionality.
      </p>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <!-- Basic Drawer -->
      <div class="rounded-lg border p-6">
        <h3 class="mb-2 font-semibold">Basic Drawer</h3>
        <p class="text-muted-foreground mb-4 text-sm">
          Simple drawer with default handle. Swipe down to close.
        </p>
        <button
          class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
          @click="openBasicDrawer"
        >
          Open Basic Drawer
        </button>

        <p class="mt-2 text-xs text-muted-foreground">State: {{ basicDrawerOpen }}</p>

        <SlideDrawer v-model:open="basicDrawerOpen">
          <SlideDrawerHeader>
            <SlideDrawerTitle>Basic Drawer</SlideDrawerTitle>
            <SlideDrawerDescription>
              This is a basic drawer with default styling.
            </SlideDrawerDescription>
          </SlideDrawerHeader>
          <SlideDrawerBody>
            <p>Swipe down on the handle or overlay to close this drawer.</p>
          </SlideDrawerBody>
        </SlideDrawer>
      </div>

      <!-- Header & Footer Drawer -->
      <div class="rounded-lg border p-6">
        <h3 class="mb-2 font-semibold">With Header & Footer</h3>
        <p class="text-muted-foreground mb-4 text-sm">
          Drawer with header, body, and sticky footer buttons.
        </p>
        <Button @click="headerFooterDrawerOpen = true">Open With Footer</Button>

        <SlideDrawer v-model:open="headerFooterDrawerOpen">
          <SlideDrawerHeader>
            <SlideDrawerTitle>Edit Profile</SlideDrawerTitle>
            <SlideDrawerDescription>
              Make changes to your profile here. Click save when you're done.
            </SlideDrawerDescription>
          </SlideDrawerHeader>
          <SlideDrawerBody>
            <div class="grid gap-4">
              <div class="grid gap-2">
                <label class="text-sm font-medium">Name</label>
                <input
                  type="text"
                  placeholder="John Doe"
                  class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                />
              </div>
              <div class="grid gap-2">
                <label class="text-sm font-medium">Username</label>
                <input
                  type="text"
                  placeholder="@johndoe"
                  class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                />
              </div>
            </div>
          </SlideDrawerBody>
          <SlideDrawerFooter>
            <Button variant="outline" @click="headerFooterDrawerOpen = false">Cancel</Button>
            <Button @click="headerFooterDrawerOpen = false">Save Changes</Button>
          </SlideDrawerFooter>
        </SlideDrawer>
      </div>

      <!-- Scrollable Content -->
      <div class="rounded-lg border p-6">
        <h3 class="mb-2 font-semibold">Scrollable Content</h3>
        <p class="text-muted-foreground mb-4 text-sm">
          Drawer with lots of content that needs scrolling.
        </p>
        <Button @click="scrollableDrawerOpen = true">Open Scrollable</Button>

        <SlideDrawer v-model:open="scrollableDrawerOpen">
          <SlideDrawerHeader>
            <SlideDrawerTitle>Terms of Service</SlideDrawerTitle>
            <SlideDrawerDescription>
              Please read our terms of service carefully.
            </SlideDrawerDescription>
          </SlideDrawerHeader>
          <SlideDrawerBody class="max-h-[60vh] overflow-y-auto">
            <div class="space-y-4">
              <p v-for="i in 15" :key="i">
                Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor
                incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud
                exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute
                irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla
                pariatur. {{ i }}
              </p>
            </div>
          </SlideDrawerBody>
          <SlideDrawerFooter>
            <Button variant="outline" @click="scrollableDrawerOpen = false">Decline</Button>
            <Button @click="scrollableDrawerOpen = false">Accept</Button>
          </SlideDrawerFooter>
        </SlideDrawer>
      </div>

      <!-- Form Drawer -->
      <div class="rounded-lg border p-6">
        <h3 class="mb-2 font-semibold">Form Drawer</h3>
        <p class="text-muted-foreground mb-4 text-sm">
          Drawer containing a form with validation.
        </p>
        <Button @click="formDrawerOpen = true">Open Form</Button>

        <SlideDrawer v-model:open="formDrawerOpen">
          <form @submit.prevent="handleFormSubmit">
            <SlideDrawerHeader>
              <SlideDrawerTitle>Contact Us</SlideDrawerTitle>
              <SlideDrawerDescription>
                Send us a message and we'll get back to you.
              </SlideDrawerDescription>
            </SlideDrawerHeader>
            <SlideDrawerBody>
              <div class="grid gap-4">
                <div class="grid gap-2">
                  <label class="text-sm font-medium">Name *</label>
                  <input
                    v-model="formData.name"
                    type="text"
                    required
                    placeholder="Your name"
                    class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                  />
                </div>
                <div class="grid gap-2">
                  <label class="text-sm font-medium">Email *</label>
                  <input
                    v-model="formData.email"
                    type="email"
                    required
                    placeholder="you@example.com"
                    class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                  />
                </div>
                <div class="grid gap-2">
                  <label class="text-sm font-medium">Message *</label>
                  <textarea
                    v-model="formData.message"
                    required
                    rows="4"
                    placeholder="Your message..."
                    class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                  />
                </div>
              </div>
            </SlideDrawerBody>
            <SlideDrawerFooter>
              <Button type="button" variant="outline" @click="formDrawerOpen = false">
                Cancel
              </Button>
              <Button type="submit">Send Message</Button>
            </SlideDrawerFooter>
          </form>
        </SlideDrawer>
      </div>

      <!-- Confirmation Drawer -->
      <div class="rounded-lg border p-6">
        <h3 class="mb-2 font-semibold">Confirmation Drawer</h3>
        <p class="text-muted-foreground mb-4 text-sm">
          Drawer for confirming destructive actions.
        </p>
        <Button variant="destructive" @click="confirmDrawerOpen = true">Delete Item</Button>

        <SlideDrawer v-model:open="confirmDrawerOpen">
          <SlideDrawerHeader>
            <SlideDrawerTitle>Are you sure?</SlideDrawerTitle>
            <SlideDrawerDescription>
              This action cannot be undone. This will permanently delete your account and remove
              your data from our servers.
            </SlideDrawerDescription>
          </SlideDrawerHeader>
          <SlideDrawerBody>
            <div class="rounded-lg border border-destructive/20 bg-destructive/10 p-4">
              <div class="flex items-center gap-3">
                <Icon name="lucide:alert-triangle" class="size-5 text-destructive" />
                <p class="text-sm text-destructive">
                  All associated data will be permanently removed.
                </p>
              </div>
            </div>
          </SlideDrawerBody>
          <SlideDrawerFooter>
            <Button variant="outline" @click="confirmDrawerOpen = false">Cancel</Button>
            <Button variant="destructive" @click="handleConfirm">
              <Icon name="lucide:trash-2" class="mr-2 size-4" />
              Delete
            </Button>
          </SlideDrawerFooter>
        </SlideDrawer>
      </div>

      <!-- No Handle Drawer -->
      <div class="rounded-lg border p-6">
        <h3 class="mb-2 font-semibold">No Handle</h3>
        <p class="text-muted-foreground mb-4 text-sm">
          Drawer without the drag handle indicator.
        </p>
        <Button @click="noHandleDrawerOpen = true">Open Without Handle</Button>

        <SlideDrawer v-model:open="noHandleDrawerOpen" :show-handle="false">
          <SlideDrawerHeader class="pt-4">
            <SlideDrawerTitle>No Handle Drawer</SlideDrawerTitle>
            <SlideDrawerDescription>
              This drawer has no drag handle. You can still swipe down to close or tap outside.
            </SlideDrawerDescription>
          </SlideDrawerHeader>
          <SlideDrawerBody>
            <p>Content without the handle indicator.</p>
          </SlideDrawerBody>
          <SlideDrawerFooter>
            <Button @click="noHandleDrawerOpen = false">Close</Button>
          </SlideDrawerFooter>
        </SlideDrawer>
      </div>

      <!-- Profile Drawer -->
      <div class="rounded-lg border p-6">
        <h3 class="mb-2 font-semibold">Profile Card</h3>
        <p class="text-muted-foreground mb-4 text-sm">
          Drawer showing a user profile card.
        </p>
        <Button @click="profileDrawerOpen = true">View Profile</Button>

        <SlideDrawer v-model:open="profileDrawerOpen">
          <SlideDrawerBody class="pb-6">
            <div class="flex flex-col items-center text-center">
              <div class="mb-4 size-20 rounded-full bg-gradient-to-br from-purple-500 to-pink-500" />
              <h3 class="text-xl font-semibold">John Doe</h3>
              <p class="text-muted-foreground">@johndoe</p>
              <p class="text-muted-foreground mt-2 text-sm">
                Full-stack developer. Building amazing things with Vue & Nuxt.
              </p>
              <div class="mt-4 flex gap-6 text-sm">
                <div class="text-center">
                  <div class="font-semibold">1.2K</div>
                  <div class="text-muted-foreground">Followers</div>
                </div>
                <div class="text-center">
                  <div class="font-semibold">456</div>
                  <div class="text-muted-foreground">Following</div>
                </div>
                <div class="text-center">
                  <div class="font-semibold">89</div>
                  <div class="text-muted-foreground">Posts</div>
                </div>
              </div>
              <div class="mt-6 flex w-full gap-2">
                <Button class="flex-1">Follow</Button>
                <Button variant="outline" class="flex-1">Message</Button>
              </div>
            </div>
          </SlideDrawerBody>
        </SlideDrawer>
      </div>

      <!-- Settings Drawer -->
      <div class="rounded-lg border p-6">
        <h3 class="mb-2 font-semibold">Settings Panel</h3>
        <p class="text-muted-foreground mb-4 text-sm">
          Drawer with toggle switches and settings.
        </p>
        <Button @click="settingsDrawerOpen = true">
          <Icon name="lucide:settings" class="mr-2 size-4" />
          Settings
        </Button>

        <SlideDrawer v-model:open="settingsDrawerOpen">
          <SlideDrawerHeader>
            <SlideDrawerTitle>Settings</SlideDrawerTitle>
            <SlideDrawerDescription>
              Customize your experience.
            </SlideDrawerDescription>
          </SlideDrawerHeader>
          <SlideDrawerBody>
            <div class="space-y-4">
              <div class="flex items-center justify-between rounded-lg border p-4">
                <div>
                  <div class="font-medium">Notifications</div>
                  <div class="text-muted-foreground text-sm">Receive push notifications</div>
                </div>
                <button
                  type="button"
                  role="switch"
                  :aria-checked="notifications"
                  :class="[
                    'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors',
                    notifications ? 'bg-primary' : 'bg-muted',
                  ]"
                  @click="notifications = !notifications"
                >
                  <span
                    :class="[
                      'pointer-events-none block size-5 rounded-full bg-background shadow-lg ring-0 transition-transform',
                      notifications ? 'translate-x-5' : 'translate-x-0',
                    ]"
                  />
                </button>
              </div>
              <div class="flex items-center justify-between rounded-lg border p-4">
                <div>
                  <div class="font-medium">Dark Mode</div>
                  <div class="text-muted-foreground text-sm">Use dark theme</div>
                </div>
                <button
                  type="button"
                  role="switch"
                  :aria-checked="darkMode"
                  :class="[
                    'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors',
                    darkMode ? 'bg-primary' : 'bg-muted',
                  ]"
                  @click="darkMode = !darkMode"
                >
                  <span
                    :class="[
                      'pointer-events-none block size-5 rounded-full bg-background shadow-lg ring-0 transition-transform',
                      darkMode ? 'translate-x-5' : 'translate-x-0',
                    ]"
                  />
                </button>
              </div>
              <div class="flex items-center justify-between rounded-lg border p-4">
                <div>
                  <div class="font-medium">Language</div>
                  <div class="text-muted-foreground text-sm">Select your language</div>
                </div>
                <select
                  v-model="language"
                  class="border-input bg-background rounded-md border px-3 py-1 text-sm"
                >
                  <option value="id">Indonesia</option>
                  <option value="en">English</option>
                  <option value="ja">Japanese</option>
                </select>
              </div>
            </div>
          </SlideDrawerBody>
          <SlideDrawerFooter>
            <Button @click="settingsDrawerOpen = false">Done</Button>
          </SlideDrawerFooter>
        </SlideDrawer>
      </div>

      <!-- Nested Drawer -->
      <div class="rounded-lg border p-6">
        <h3 class="mb-2 font-semibold">Nested Drawer</h3>
        <p class="text-muted-foreground mb-4 text-sm">
          Open another drawer from inside a drawer.
        </p>
        <Button @click="nestedDrawerOpen = true">Open Nested</Button>

        <SlideDrawer v-model:open="nestedDrawerOpen">
          <SlideDrawerHeader>
            <SlideDrawerTitle>First Drawer</SlideDrawerTitle>
            <SlideDrawerDescription>
              This is the first drawer. Click the button to open another.
            </SlideDrawerDescription>
          </SlideDrawerHeader>
          <SlideDrawerBody>
            <Button @click="innerNestedDrawerOpen = true">Open Inner Drawer</Button>
          </SlideDrawerBody>
          <SlideDrawerFooter>
            <Button variant="outline" @click="nestedDrawerOpen = false">Close</Button>
          </SlideDrawerFooter>
        </SlideDrawer>

        <SlideDrawer v-model:open="innerNestedDrawerOpen">
          <SlideDrawerHeader>
            <SlideDrawerTitle>Inner Drawer</SlideDrawerTitle>
            <SlideDrawerDescription>
              This is the inner drawer opened from the first one.
            </SlideDrawerDescription>
          </SlideDrawerHeader>
          <SlideDrawerBody>
            <p>You can close this drawer by swiping down or clicking the button below.</p>
          </SlideDrawerBody>
          <SlideDrawerFooter>
            <Button @click="innerNestedDrawerOpen = false">Close Inner</Button>
          </SlideDrawerFooter>
        </SlideDrawer>
      </div>
    </div>
  </div>
</template>

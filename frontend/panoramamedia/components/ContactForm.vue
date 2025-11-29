<template>
  <div>
    <div v-if="!isMessageSent">
      <h1
        class="text-primary text-3xl leading-[1.25] font-semibold tracking-tighter text-balance sm:text-5xl sm:leading-[1.25]"
      >
        {{ props.title ?? "Contact us" }}
      </h1>

      <p v-if="props.description" class="mt-2 tracking-tight sm:mt-3">
        {{ props.description }}
      </p>

      <form
        @submit.prevent="sendMessage"
        id="contact-form"
        class="mt-8 grid gap-6"
      >
        <div class="input-group">
          <label>Name</label>
          <input
            v-model="form.name"
            type="text"
            name="name"
            id="name"
            required
          />
        </div>

        <div v-if="showJobTitleField" class="input-group">
          <label>Job Title</label>
          <input
            v-model="form.jobTitle"
            type="text"
            name="jobTitle"
            id="jobTitle"
          />
        </div>

        <div v-if="showBrandField" class="input-group">
          <label>Brand / Company Name</label>
          <input
            v-model="form.company"
            type="text"
            name="company"
            id="company"
          />
        </div>

        <div v-if="showProductsField" class="input-group">
          <label>Products / Services</label>
          <input
            v-model="form.products"
            type="text"
            name="products"
            id="products"
          />
        </div>

        <div class="input-group">
          <label>Email</label>
          <input
            v-model="form.email"
            type="text"
            name="email"
            id="email"
            required
          />
        </div>
        <div class="input-group">
          <label>Phone (WhatsApp)</label>
          <PhoneInputField
            v-model="form.phone"
            id="phone"
            name="phone"
            required
          />
        </div>
        <div v-if="!props.message" class="input-group">
          <label>Message</label>
          <textarea
            v-model="form.message"
            name="message"
            id="message"
            class="autogrow"
            placeholder="Leave a message.."
            required
          ></textarea>
        </div>

        <p
          v-if="props.title == 'Exhibitor Registration'"
          class="text-muted-foreground text-sm tracking-tight"
        >
          Do not worry. By submitting this form, you are not automatically
          registered as an exhibitor. We need your contact information so our
          sales team can provide you with further details about available
          booths, pricing, and more.
        </p>

        <p v-else class="text-muted-foreground text-sm tracking-tight">
          Please ensure you put the correct and active email address and phone
          number. Our team will reach you soon.
        </p>

        <button
          type="submit"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center justify-center gap-2 justify-self-start rounded-xl px-4 py-3 text-sm font-semibold tracking-tight transition active:scale-98"
          :disabled="loading"
          v-ripple
        >
          <LoadingSpinner v-if="loading" class="border-background size-4" />
          <span>{{ buttonLabel ?? "Send message" }}</span>
        </button>
      </form>
    </div>

    <div v-else>
      <div
        class="min-h-screen-offset -mt-16 flex flex-col items-center justify-center text-center"
      >
        <div
          class="bg-muted flex size-16 items-center justify-center rounded-full"
        >
          <Icon
            name="hugeicons:checkmark-circle-03"
            class="text-primary size-8"
          />
        </div>

        <h2
          class="text-primary text-4xl font-semibold tracking-tighter text-balance sm:text-5xl xl:text-6xl"
        >
          Thank you! Your request has been successfully submitted.
        </h2>
        <p class="mt-4">
          Our team will contact you via email or WhatsApp as soon as possible.
        </p>

        <nuxt-link
          to="/"
          class="bg-primary text-primary-foreground mt-8 rounded-xl px-6 py-4 font-semibold tracking-tight"
          v-ripple
          @click="closeInquiryDialog"
        >
          Okay. Send me back to home
        </nuxt-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const { gtag } = useGtag();

const props = defineProps({
  title: String,
  description: String,
  showBrandField: Boolean,
  showJobTitleField: Boolean,
  showProductsField: Boolean,
  message: String,
  buttonLabel: String,
});

const uiStore = useUiStore();
const closeInquiryDialog = () => {
  uiStore.closeInquiryDialog();
};

const isMessageSent = ref(false);
const loading = ref(false);
const form = reactive({
  name: "",
  jobTitle: "",
  company: "",
  products: "",
  email: "",
  phone: "",
  message: "",
});

const sendMessage = async () => {
  loading.value = true;

  try {
    const config = useAppConfig();

    // Prepare form data for PM One API
    const formData = {
      name: form.name,
      email: form.email,
      phone: form.phone,
      message: props.message ?? form.message,
    };

    // Add optional fields if they exist
    if (form.jobTitle) {
      formData.job_title = form.jobTitle;
    }
    if (form.company) {
      formData.company = form.company;
    }
    if (form.products) {
      formData.products = form.products;
    }

    // PM One API endpoint
    const endpoint = `${config.app.pmOneApiUrl}/api/contact-forms/submit`;

    const response = await fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        project_username: "panoramamedia", // Your project username in PM One
        subject: `Contact Form - ${config.app.shortName}`,
        data: formData,
      }),
    });

    const result = await response.json();

    if (result.success) {
      // Success - reset form
      form.name = "";
      form.jobTitle = "";
      form.company = "";
      form.products = "";
      form.email = "";
      form.phone = "";
      form.message = "";

      gtag("event", "contact_form_submission");

      isMessageSent.value = true;
    } else {
      // Error from PM One API
      toast.error(result.message || "Failed to send message. Please try again.");
    }
  } catch (error) {
    console.error("Contact form error:", error);
    toast.error("Network error. Please try again later.");
  } finally {
    loading.value = false;
  }
};
</script>

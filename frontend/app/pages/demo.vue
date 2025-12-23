<template>
  <div class="glass-demo-page bg-background">
    <!-- Main Content -->
    <div class="glass-demo-content">
      <!-- Variants Section -->
      <section class="glass-variants-section">
        <h2 class="glass-section-title">Variants</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton variant="default">Default</GlassButton>
            <span class="glass-variant-label">default</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="secondary">Secondary</GlassButton>
            <span class="glass-variant-label">secondary</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive">Destructive</GlassButton>
            <span class="glass-variant-label">destructive</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="destructive-outline">Destructive</GlassButton>
            <span class="glass-variant-label">destructive-outline</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="outline">Outline</GlassButton>
            <span class="glass-variant-label">outline</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="ghost">Ghost</GlassButton>
            <span class="glass-variant-label">ghost</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton variant="link">Link</GlassButton>
            <span class="glass-variant-label">link</span>
          </div>
        </div>

        <h2 class="glass-section-title">Sizes</h2>
        <div class="glass-variants-grid glass-variants-grid-sizes">
          <div class="glass-variant-item">
            <GlassButton size="xs">Extra Small</GlassButton>
            <span class="glass-variant-label">xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton size="sm">Small</GlassButton>
            <span class="glass-variant-label">sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton size="default">Default</GlassButton>
            <span class="glass-variant-label">default</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton size="lg">Large</GlassButton>
            <span class="glass-variant-label">lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton size="xl">Extra Large</GlassButton>
            <span class="glass-variant-label">xl</span>
          </div>
        </div>

        <h2 class="glass-section-title">Icon Sizes</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton size="icon-xs">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M5 12h14" />
                <path d="M12 5v14" />
              </svg>
            </GlassButton>
            <span class="glass-variant-label">icon-xs</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton size="icon-sm">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M5 12h14" />
                <path d="M12 5v14" />
              </svg>
            </GlassButton>
            <span class="glass-variant-label">icon-sm</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton size="icon">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M5 12h14" />
                <path d="M12 5v14" />
              </svg>
            </GlassButton>
            <span class="glass-variant-label">icon</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton size="icon-lg">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M5 12h14" />
                <path d="M12 5v14" />
              </svg>
            </GlassButton>
            <span class="glass-variant-label">icon-lg</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton size="icon-xl">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="28"
                height="28"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M5 12h14" />
                <path d="M12 5v14" />
              </svg>
            </GlassButton>
            <span class="glass-variant-label">icon-xl</span>
          </div>
        </div>

        <h2 class="glass-section-title">States</h2>
        <div class="glass-variants-grid">
          <div class="glass-variant-item">
            <GlassButton>Normal</GlassButton>
            <span class="glass-variant-label">default</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton disabled>Disabled</GlassButton>
            <span class="glass-variant-label">disabled</span>
          </div>
          <div class="glass-variant-item">
            <GlassButton :loading="true">Loading</GlassButton>
            <span class="glass-variant-label">loading</span>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { GlassButton } from "@/components/ui/glass-button";
</script>

<style scoped>
.glass-demo-page {
  position: relative;
  min-height: 100vh;
  width: 100%;
  overflow-x: hidden;
}

.glass-demo-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 4rem 2rem;
  gap: 4rem;
}

/* Variants Section */
.glass-variants-section {
  width: 100%;
  max-width: 1200px;
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.glass-section-title {
  font-size: 1.25rem;
  font-weight: 600;
  text-align: center;
  margin-bottom: 0.5rem;
}

.glass-variants-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 2rem;
  padding: 2rem;
  border-radius: 1.5rem;
  border: 1px solid var(--color-border / 0.3);
}

.glass-variants-grid-sizes {
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
}

.glass-variant-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}

.glass-variant-label {
  font-size: 0.75rem;
  font-family: ui-monospace, monospace;
  color: var(--color-muted-foreground);
}
</style>

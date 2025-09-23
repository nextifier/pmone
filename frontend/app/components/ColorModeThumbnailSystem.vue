<template>
  <svg viewBox="0 0 64 54" fill="none" xmlns="http://www.w3.org/2000/svg">
    <g clip-path="url(#clip0_168_1700)">
      <!-- Light side (left half) -->
      <rect x="0" y="0" width="32" height="54" fill="var(--color-white)" />

      <!-- Dark side (right half) -->
      <rect x="32" y="0" width="32" height="54" fill="var(--color-gray-950)" />

      <!-- Traffic light circles (consistent across both sides) -->
      <circle cx="8.2832" cy="9.08838" r="2" fill="#ED6B5D" />
      <circle cx="14.95" cy="9.08838" r="2" fill="#F4BE50" />
      <circle cx="21.6167" cy="9.08838" r="2" fill="#61C554" />

      <!-- Main content area - light side -->
      <path
        d="M6 22C6 17.5817 9.58172 14 14 14H32V54H6V22Z"
        fill="var(--color-gray-100)"
      />

      <!-- Main content area - dark side -->
      <path
        d="M32 14H50C54.4183 14 58 17.5817 58 22V54H32V14Z"
        fill="var(--color-gray-900)"
      />

      <!-- Content blocks - light side -->
      <path
        d="M18 26C18 23.7909 19.7909 22 22 22H32V30H22C19.7909 30 18 28.2091 18 26V26Z"
        fill="var(--color-gray-200)"
      />

      <!-- Content blocks - dark side -->
      <path
        d="M32 22H42C44.2091 22 46 23.7909 46 26V26C46 28.2091 44.2091 30 42 30H32V22Z"
        fill="var(--color-gray-800)"
      />

      <!-- Additional content blocks - light side -->
      <path
        d="M10 39C10 36.7909 11.7909 35 14 35H22C24.2091 35 26 36.7909 26 39V54H10V39Z"
        fill="var(--color-gray-200)"
      />

      <!-- Additional content blocks - dark side -->
      <path
        d="M38 39C38 36.7909 39.7909 35 42 35H50C52.2091 35 54 36.7909 54 39V54H38V39Z"
        fill="var(--color-gray-800)"
      />

      <!-- Small content block - light side -->
      <path
        d="M28 49C28 46.7909 29.7909 45 32 45V54H28V49Z"
        fill="var(--color-gray-200)"
      />

      <!-- Small content block - dark side -->
      <path
        d="M32 45C34.2091 45 36 46.7909 36 49V54H32V45Z"
        fill="var(--color-gray-800)"
      />

      <!-- Button element - spanning the center line -->
      <rect
        x="26"
        y="42"
        width="14"
        height="7"
        rx="3.5"
        fill="var(--color-gray-400)"
      />

      <!-- Divider line in the center -->
      <line
        x1="32"
        y1="0"
        x2="32"
        y2="54"
        stroke="var(--color-gray-300)"
        stroke-width="0.5"
        opacity="0.5"
      />
    </g>
    <defs>
      <clipPath id="clip0_168_1700">
        <rect width="64" height="54" />
      </clipPath>
    </defs>
  </svg>
</template>
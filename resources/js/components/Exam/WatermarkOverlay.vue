<script setup lang="ts">
defineProps<{
    enabled: boolean;
    studentName: string;
}>();

function generateSvgDataUri(name: string): string {
    const truncated = name.length > 30 ? name.substring(0, 30) : name;
    const encoded = truncated
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="250" height="200">
        <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
              font-family="sans-serif" font-size="14" fill="currentColor"
              transform="rotate(-30, 125, 100)">${encoded}</text>
    </svg>`;

    return `url("data:image/svg+xml,${encodeURIComponent(svg)}")`;
}
</script>

<template>
    <div
        v-if="enabled"
        aria-hidden="true"
        class="exam-wm"
        :style="{ backgroundImage: generateSvgDataUri(studentName) }"
    />
</template>

<style scoped>
.exam-wm {
    position: fixed !important;
    inset: 0 !important;
    z-index: 9999 !important;
    pointer-events: none !important;
    user-select: none !important;
    -webkit-user-select: none !important;
    opacity: 0.04;
    background-repeat: repeat;
    background-size: 250px 200px;
    color: #000;
}

@media print {
    .exam-wm {
        opacity: 0.08 !important;
    }
}

@media (prefers-color-scheme: dark) {
    .exam-wm {
        color: #fff;
    }
}

.dark .exam-wm {
    color: #fff;
}
</style>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const appSettings = computed(() => (page.props as any).app_settings ?? {});
const appName = computed(() => appSettings.value.app_name || 'SMK LMS');
const logoPath = computed(() => {
    const path = appSettings.value.logo_path;
    if (!path) return null;
    if (path.startsWith('http')) return path;
    if (path.startsWith('images/')) return `/${path}`;
    return `/storage/${path}`;
});
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <img v-if="logoPath" :src="logoPath" :alt="appName" class="size-5 object-contain" />
        <AppLogoIcon v-else class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{ appName }}</span>
    </div>
</template>

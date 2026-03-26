<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const appSettings = computed(() => (page.props as any).app_settings ?? {});
const footerText = computed(() => appSettings.value.footer_text || '');
const showPoweredBy = computed(() => appSettings.value.show_powered_by ?? true);
const appName = computed(() => appSettings.value.app_name || 'SMK LMS');
</script>

<template>
    <footer v-if="footerText || showPoweredBy" class="border-t border-sidebar-border px-6 py-3 text-xs text-muted-foreground">
        <div class="flex items-center justify-between gap-4">
            <span v-if="footerText">{{ footerText }}</span>
            <span v-if="showPoweredBy" class="ml-auto">Powered by {{ appName }}</span>
        </div>
    </footer>
</template>

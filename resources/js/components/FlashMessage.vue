<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CircleCheck, CircleX } from 'lucide-vue-next';

const page = usePage();

const flash = computed(
    () => page.props.flash as { success?: string; error?: string },
);
</script>

<template>
    <Alert v-if="flash.success" variant="default" class="mb-4 border-green-500/50 text-green-700 dark:text-green-400">
        <CircleCheck class="size-4" />
        <AlertDescription>{{ flash.success }}</AlertDescription>
    </Alert>
    <Alert v-if="flash.error" variant="destructive" class="mb-4">
        <CircleX class="size-4" />
        <AlertDescription class="whitespace-pre-line">{{ flash.error }}</AlertDescription>
    </Alert>
</template>

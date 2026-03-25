<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import type { ButtonVariants } from '@/components/ui/button';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    loading?: boolean;
    disabled?: boolean;
    variant?: NonNullable<ButtonVariants['variant']>;
    size?: NonNullable<ButtonVariants['size']>;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Button
        :variant="variant ?? 'default'"
        :size="size ?? 'default'"
        :disabled="loading || disabled"
        :class="$props.class"
    >
        <Spinner v-if="loading" class="h-4 w-4 animate-spin" />
        <slot />
    </Button>
</template>

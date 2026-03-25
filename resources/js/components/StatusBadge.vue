<script setup lang="ts">
import { Badge, type BadgeVariants } from '@/components/ui/badge';

defineProps<{
    label: string;
    variant?: NonNullable<BadgeVariants['variant']>;
}>();
</script>

<template>
    <Badge :variant="variant ?? 'default'">
        <slot>{{ label }}</slot>
    </Badge>
</template>

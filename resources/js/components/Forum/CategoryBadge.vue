<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { ForumCategory } from '@/types';

const props = defineProps<{
    category: ForumCategory | null | undefined;
}>();

const style = computed(() => {
    if (!props.category?.color) return {};
    return {
        backgroundColor: `${props.category.color}20`,
        color: props.category.color,
        borderColor: `${props.category.color}40`,
    };
});
</script>

<template>
    <Badge v-if="category" variant="outline" class="text-xs" :style="style">
        {{ category.name }}
    </Badge>
    <Badge v-else variant="secondary" class="text-xs">Umum</Badge>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { PaginationLink } from '@/types';

defineProps<{
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}>();
</script>

<template>
    <div class="flex items-center justify-between px-2 py-3">
        <p class="text-sm text-muted-foreground">
            <template v-if="from && to">
                Menampilkan {{ from }} - {{ to }} dari {{ total }}
            </template>
            <template v-else> Tidak ada data </template>
        </p>
        <div class="flex items-center gap-1">
            <template v-for="link in links" :key="link.label">
                <Button
                    v-if="link.url"
                    :variant="link.active ? 'default' : 'outline'"
                    size="sm"
                    as-child
                >
                    <Link :href="link.url" preserve-scroll v-html="link.label" />
                </Button>
                <Button
                    v-else
                    variant="outline"
                    size="sm"
                    disabled
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>

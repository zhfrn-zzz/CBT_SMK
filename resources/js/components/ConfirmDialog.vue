<script setup lang="ts">
import { ref } from 'vue';
import { AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        confirmLabel?: string;
        cancelLabel?: string;
        variant?: 'destructive' | 'default';
    }>(),
    {
        title: 'Apakah Anda yakin?',
        description: 'Tindakan ini tidak dapat dibatalkan.',
        confirmLabel: 'Ya, Lanjutkan',
        cancelLabel: 'Batal',
        variant: 'destructive',
    },
);

const emit = defineEmits<{
    confirm: [];
}>();

const isOpen = ref(false);

function handleConfirm() {
    emit('confirm');
    isOpen.value = false;
}
</script>

<template>
    <AlertDialog v-model:open="isOpen">
        <AlertDialogTrigger as-child>
            <slot />
        </AlertDialogTrigger>
        <AlertDialogContent class="max-w-[calc(100vw-2rem)] sm:max-w-lg">
            <AlertDialogHeader>
                <div class="flex items-center gap-3">
                    <div
                        :class="[
                            'flex h-10 w-10 items-center justify-center rounded-full',
                            variant === 'destructive' ? 'bg-red-50 dark:bg-red-950' : 'bg-primary/10',
                        ]"
                    >
                        <AlertTriangle
                            :class="[
                                'h-5 w-5',
                                variant === 'destructive' ? 'text-destructive' : 'text-primary',
                            ]"
                        />
                    </div>
                    <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                </div>
                <AlertDialogDescription class="ml-[52px]">
                    {{ description }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ cancelLabel }}</AlertDialogCancel>
                <AlertDialogAction
                    :class="variant === 'destructive' ? 'bg-destructive text-white hover:bg-red-600' : ''"
                    @click="handleConfirm"
                >
                    {{ confirmLabel }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

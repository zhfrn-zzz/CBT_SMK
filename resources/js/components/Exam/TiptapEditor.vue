<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Bold,
    Code,
    Heading2,
    ImagePlus,
    Italic,
    List,
    ListOrdered,
    Redo,
    Strikethrough,
    Undo,
} from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Image.configure({
            inline: true,
            allowBase64: false,
        }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm dark:prose-invert max-w-none min-h-[120px] px-3 py-2 focus:outline-none',
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(() => props.modelValue, (newValue) => {
    if (editor.value && editor.value.getHTML() !== newValue) {
        editor.value.commands.setContent(newValue, { emitUpdate: false });
    }
});

async function uploadImage() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async () => {
        const file = input.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('image', file);

        try {
            const response = await fetch('/guru/soal/upload-image', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                const data = await response.json();
                editor.value?.chain().focus().setImage({ src: data.url }).run();
            }
        } catch {
            // silently fail
        }
    };
    input.click();
}
</script>

<template>
    <div class="rounded-md border">
        <!-- Toolbar -->
        <div v-if="editor" class="flex flex-wrap gap-0.5 border-b p-1">
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :class="{ 'bg-muted': editor.isActive('bold') }"
                @click="editor.chain().focus().toggleBold().run()"
            >
                <Bold class="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :class="{ 'bg-muted': editor.isActive('italic') }"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                <Italic class="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :class="{ 'bg-muted': editor.isActive('strike') }"
                @click="editor.chain().focus().toggleStrike().run()"
            >
                <Strikethrough class="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :class="{ 'bg-muted': editor.isActive('code') }"
                @click="editor.chain().focus().toggleCode().run()"
            >
                <Code class="size-4" />
            </Button>

            <div class="mx-1 w-px bg-border" />

            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :class="{ 'bg-muted': editor.isActive('heading', { level: 2 }) }"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                <Heading2 class="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :class="{ 'bg-muted': editor.isActive('bulletList') }"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                <List class="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :class="{ 'bg-muted': editor.isActive('orderedList') }"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="size-4" />
            </Button>

            <div class="mx-1 w-px bg-border" />

            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                @click="uploadImage"
            >
                <ImagePlus class="size-4" />
            </Button>

            <div class="mx-1 w-px bg-border" />

            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :disabled="!editor.can().undo()"
                @click="editor.chain().focus().undo().run()"
            >
                <Undo class="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :disabled="!editor.can().redo()"
                @click="editor.chain().focus().redo().run()"
            >
                <Redo class="size-4" />
            </Button>
        </div>

        <!-- Editor Content -->
        <EditorContent :editor="editor" />
    </div>
</template>

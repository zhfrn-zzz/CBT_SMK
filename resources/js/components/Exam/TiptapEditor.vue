<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Bold,
    Code,
    Heading2,
    ImagePlus,
    Italic,
    List,
    ListOrdered,
    Loader2,
    Redo,
    Strikethrough,
    Undo,
} from 'lucide-vue-next';

const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const uploading = ref(false);

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

function uploadImage() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = ALLOWED_TYPES.join(',');
    input.onchange = () => {
        const file = input.files?.[0];
        if (!file) return;

        if (!ALLOWED_TYPES.includes(file.type)) {
            toast.error('Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.');
            return;
        }

        if (file.size > MAX_FILE_SIZE) {
            toast.error(`Ukuran file terlalu besar (maks 2MB). File Anda: ${(file.size / 1024 / 1024).toFixed(1)}MB.`);
            return;
        }

        doUpload(file);
    };
    input.click();
}

async function doUpload(file: File) {
    uploading.value = true;
    const toastId = toast.loading('Mengupload gambar...');

    const formData = new FormData();
    formData.append('image', file);

    try {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        const response = await fetch('/guru/soal/upload-image', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => null);
            const message = errorData?.message ?? `Upload gagal (${response.status})`;
            toast.error(message, { id: toastId });
            return;
        }

        const data = await response.json();
        editor.value?.chain().focus().setImage({ src: data.url }).run();
        toast.success('Gambar berhasil diupload.', { id: toastId });
    } catch {
        toast.error('Upload gagal. Periksa koneksi Anda.', { id: toastId });
    } finally {
        uploading.value = false;
    }
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
                :disabled="uploading"
                @click="uploadImage"
            >
                <Loader2 v-if="uploading" class="size-4 animate-spin" />
                <ImagePlus v-else class="size-4" />
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

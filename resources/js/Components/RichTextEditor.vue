<script setup>
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue'])

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit,
  ],
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  }
})
</script>

<template>
  <div class="rich-text-editor">
    <div class="border border-gray-300 rounded-md">
      <div class="border-b border-gray-300 bg-gray-50 px-3 py-2">
        <div class="flex space-x-2">
          <button
            @click="editor?.chain().focus().toggleBold().run()"
            :class="{ 'bg-gray-200': editor?.isActive('bold') }"
            class="p-1 rounded hover:bg-gray-200"
            type="button"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path></svg>
          </button>
          <button
            @click="editor?.chain().focus().toggleItalic().run()"
            :class="{ 'bg-gray-200': editor?.isActive('italic') }"
            class="p-1 rounded hover:bg-gray-200"
            type="button"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><line x1="19" y1="4" x2="10" y2="4"></line><line x1="14" y1="20" x2="5" y2="20"></line><line x1="15" y1="4" x2="9" y2="20"></line></svg>
          </button>
          <button
            @click="editor?.chain().focus().toggleBulletList().run()"
            :class="{ 'bg-gray-200': editor?.isActive('bulletList') }"
            class="p-1 rounded hover:bg-gray-200"
            type="button"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
          </button>
          <button
            @click="editor?.chain().focus().toggleOrderedList().run()"
            :class="{ 'bg-gray-200': editor?.isActive('orderedList') }"
            class="p-1 rounded hover:bg-gray-200"
            type="button"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></line><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg>
          </button>
        </div>
      </div>
      <EditorContent :editor="editor" class="prose max-w-none px-3 py-2 min-h-[150px]" />
    </div>
  </div>
</template>

<style>
.rich-text-editor .ProseMirror {
  outline: none;
}

.rich-text-editor .ProseMirror p {
  margin: 0;
}

.rich-text-editor .ProseMirror ul {
  list-style-type: disc;
  padding-left: 1.5em;
}

.rich-text-editor .ProseMirror ol {
  list-style-type: decimal;
  padding-left: 1.5em;
}
</style> 
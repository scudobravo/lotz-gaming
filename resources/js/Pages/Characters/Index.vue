<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modal.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'
import { ref } from 'vue'

const props = defineProps({
  characters: {
    type: Array,
    required: true
  }
})

const showDeleteModal = ref(false)
const characterToDelete = ref(null)

const form = useForm({
  _method: 'DELETE'
})

const confirmDelete = (character) => {
  characterToDelete.value = character
  showDeleteModal.value = true
}

const closeDeleteModal = () => {
  showDeleteModal.value = false
  characterToDelete.value = null
}

const deleteCharacter = () => {
  if (characterToDelete.value) {
    form.delete(route('characters.destroy', characterToDelete.value.id), {
      onSuccess: () => {
        closeDeleteModal()
      }
    })
  }
}
</script>

<template>
  <Head title="Personaggi" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Personaggi</h2>
        <Link
          :href="route('characters.create')"
          class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
        >
          Nuovo Personaggio
        </Link>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
          <h2 class="text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl">I Nostri Personaggi</h2>
          <p class="mt-6 text-lg/8 text-gray-600">Scopri i personaggi che popolano i nostri progetti. Ogni personaggio ha una storia unica da raccontare.</p>
        </div>
        
        <ul role="list" class="mx-auto mt-20 grid max-w-2xl grid-cols-1 gap-6 sm:grid-cols-2 lg:mx-0 lg:max-w-none lg:grid-cols-3 lg:gap-8">
          <li v-for="character in characters" :key="character.id" class="rounded-2xl bg-white px-8 py-10 shadow-lg ring-1 ring-gray-200">
            <div class="flex flex-col items-center">
              <div class="size-48 md:size-56 rounded-full overflow-hidden ring-4 ring-gray-100">
                <img 
                  v-if="character.gif_url" 
                  :src="character.gif_url" 
                  :alt="character.name"
                  class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full bg-gray-200 flex items-center justify-center">
                  <span class="text-gray-400">No GIF</span>
                </div>
              </div>
              
              <h3 class="mt-6 text-xl font-semibold tracking-tight text-gray-900">{{ character.name }}</h3>
              <div class="mt-2 text-sm/6 text-gray-600 line-clamp-3" v-html="character.description"></div>
              
              <div class="mt-6 flex justify-center gap-x-4">
                <Link
                  :href="route('characters.edit', character.id)"
                  class="text-indigo-600 hover:text-indigo-900"
                >
                  <span class="sr-only">Modifica</span>
                  <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </Link>
                <button
                  @click="confirmDelete(character)"
                  class="text-red-600 hover:text-red-900"
                >
                  <span class="sr-only">Elimina</span>
                  <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Modal di conferma eliminazione -->
    <Modal :show="showDeleteModal" @close="closeDeleteModal">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900">
          Conferma eliminazione
        </h2>
        <p class="mt-1 text-sm text-gray-600">
          Sei sicuro di voler eliminare questo personaggio? Questa azione non può essere annullata.
        </p>
        <div class="mt-6 flex justify-end space-x-3">
          <SecondaryButton @click="closeDeleteModal">
            Annulla
          </SecondaryButton>
          <DangerButton
            @click="deleteCharacter"
            :class="{ 'opacity-25': form.processing }"
            :disabled="form.processing"
          >
            Elimina
          </DangerButton>
        </div>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template> 
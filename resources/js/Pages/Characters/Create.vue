<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import RichTextEditor from '@/Components/RichTextEditor.vue'
import Multiselect from '@/Components/Multiselect.vue'
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({
  projects: {
    type: Array,
    required: true
  }
})

const form = useForm({
  name: '',
  description: '',
  gif: null,
  audio: null,
  project_ids: []
})

const gifPreview = ref(null)
const audioPreview = ref(null)

const handleGifChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    console.log('File selezionato:', {
      name: file.name,
      size: file.size,
      type: file.type,
      lastModified: file.lastModified
    })
    
    // Verifica che il file sia effettivamente un GIF
    if (file.type !== 'image/gif') {
      alert('Il file deve essere un GIF')
      e.target.value = null
      return
    }

    // Verifica la dimensione del file (5MB = 5 * 1024 * 1024 bytes)
    const maxSize = 5 * 1024 * 1024
    if (file.size > maxSize) {
      alert('Il file è troppo grande. La dimensione massima consentita è 5MB')
      e.target.value = null
      return
    }
    
    form.gif = file
    gifPreview.value = URL.createObjectURL(file)
  }
}

const handleAudioChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    // Verifica la dimensione del file (30MB = 30 * 1024 * 1024 bytes)
    const maxSize = 30 * 1024 * 1024
    if (file.size > maxSize) {
      alert('Il file è troppo grande. La dimensione massima consentita è 30MB')
      e.target.value = null
      return
    }
    
    form.audio = file
  }
}

const submit = async () => {
  try {
    const formData = new FormData();
    formData.append('name', form.name);
    formData.append('description', form.description);
    
    if (form.gif instanceof File) {
      console.log('Aggiungo GIF al form:', {
        name: form.gif.name,
        size: form.gif.size,
        type: form.gif.type
      });
      
      formData.append('gif', form.gif);
      
      // Debug del file nel FormData
      const fileEntry = formData.get('gif');
      console.log('File nel FormData:', {
        isFile: fileEntry instanceof File,
        name: fileEntry instanceof File ? fileEntry.name : 'non è un file',
        size: fileEntry instanceof File ? fileEntry.size : 'N/A',
        type: fileEntry instanceof File ? fileEntry.type : 'N/A'
      });
    }
    
    if (form.audio instanceof File) {
      formData.append('audio', form.audio);
    }
    
    // Aggiungi ogni project_id individualmente
    if (form.project_ids && form.project_ids.length > 0) {
      form.project_ids.forEach(id => {
        if (id) {
          formData.append('project_ids[]', id);
        }
      });
    }

    // Debug del FormData
    for (let [key, value] of formData.entries()) {
      console.log('FormData entry:', key, value instanceof File ? 
        `${value.name} (${value.size} bytes)` : value);
    }

    console.log('Invio richiesta...');
    const response = await axios.post(
      route('characters.store'),
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        onUploadProgress: progressEvent => {
          const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total);
          console.log(`Progresso upload: ${percent}%`);
        }
      }
    );

    console.log('Risposta ricevuta:', response.data);

    if (response.data.success) {
      window.location.href = route('characters.index');
    }
  } catch (error) {
    console.error('Errore completo:', {
      error: error.response?.data || error,
      request: error.config,
      response: error.response,
      status: error.response?.status,
      statusText: error.response?.statusText,
      data: error.response?.data
    });
    
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      if (errors.gif) {
        alert(`Errore GIF: ${errors.gif.join(', ')}`);
      }
      if (errors.project_ids) {
        alert(`Errore Progetti: ${errors.project_ids.join(', ')}`);
      }
    } else {
      alert('Errore sconosciuto durante il caricamento');
    }
  }
}
</script>

<template>
  <Head title="Nuovo Personaggio" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuovo Personaggio</h2>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <div class="mb-6">
              <h3 class="text-lg leading-6 font-medium text-gray-900">Nuovo Personaggio</h3>
              <p class="mt-1 text-sm text-gray-500">Crea un nuovo personaggio per il tuo gioco interattivo.</p>
            </div>
            <form @submit.prevent="submit" class="space-y-6">
              <div>
                <InputLabel for="name" value="Nome" />
                <TextInput
                  id="name"
                  type="text"
                  class="mt-1 block w-full"
                  v-model="form.name"
                  required
                  autofocus
                />
                <InputError class="mt-2" :message="form.errors.name" />
              </div>

              <div>
                <InputLabel for="description" value="Descrizione" />
                <RichTextEditor
                  id="description"
                  class="mt-1 block w-full"
                  v-model="form.description"
                />
                <InputError class="mt-2" :message="form.errors.description" />
              </div>

              <div>
                <InputLabel for="projects" value="Progetti" />
                <Multiselect
                  id="projects"
                  class="mt-1 block w-full"
                  v-model="form.project_ids"
                  :options="projects"
                  label="name"
                  value="id"
                  placeholder="Seleziona i progetti..."
                />
                <InputError class="mt-2" :message="form.errors.project_ids" />
              </div>

              <div>
                <InputLabel for="gif" value="GIF/Immagine" />
                <input
                  type="file"
                  id="gif"
                  class="mt-1 block w-full text-sm text-gray-500
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-md file:border-0
                    file:text-sm file:font-semibold
                    file:bg-indigo-50 file:text-indigo-700
                    hover:file:bg-indigo-100"
                  @input="handleGifChange"
                  accept="image/*"
                />
                <InputError class="mt-2" :message="form.errors.gif" />
              </div>

              <div>
                <InputLabel for="audio" value="Audio" />
                <input
                  type="file"
                  id="audio"
                  class="mt-1 block w-full text-sm text-gray-500
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-md file:border-0
                    file:text-sm file:font-semibold
                    file:bg-indigo-50 file:text-indigo-700
                    hover:file:bg-indigo-100"
                  @input="handleAudioChange"
                  accept="audio/*"
                />
                <InputError class="mt-2" :message="form.errors.audio" />
              </div>

              <div class="flex items-center justify-end">
                <PrimaryButton class="ml-4" :disabled="form.processing">
                  Crea Personaggio
                </PrimaryButton>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template> 
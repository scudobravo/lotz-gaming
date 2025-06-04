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
  character: {
    type: Object,
    required: true
  },
  projects: {
    type: Array,
    required: true
  }
})

const form = useForm({
  _method: 'PUT',
  name: props.character.name,
  description: props.character.description,
  gif: null,
  audio: null,
  project_ids: props.character.projects.map(p => p.id)
})

const selectedProjects = ref(props.character.projects)

const gifPreview = ref(props.character.gif)
const audioPreview = ref(props.character.audio)

const handleProjectChange = (selectedProjects) => {
  form.project_ids = selectedProjects.map(p => p.id).filter(id => id !== null && id !== undefined)
}

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
    form.audio = file
  }
}

const submit = async () => {
  try {
    const formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('name', form.name);
    formData.append('description', form.description);
    
    if (form.gif instanceof File) {
      console.log('Aggiungo GIF al form:', {
        name: form.gif.name,
        size: form.gif.size,
        type: form.gif.type
      });
      
      // Assicurati che il file venga aggiunto con il nome corretto
      formData.append('gif', form.gif);
      
      // Debug del file nel FormData
      const fileEntry = formData.get('gif');
      console.log('File nel FormData:', {
        isFile: fileEntry instanceof File,
        name: fileEntry instanceof File ? fileEntry.name : 'non è un file',
        size: fileEntry instanceof File ? fileEntry.size : 'N/A',
        type: fileEntry instanceof File ? fileEntry.type : 'N/A'
      });
    } else {
      console.log('Nessun file GIF da inviare');
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
      route('characters.update', props.character.id),
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
  <Head title="Modifica Personaggio" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifica Personaggio</h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
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
                  v-model="form.description"
                  class="mt-1"
                />
                <InputError class="mt-2" :message="form.errors.description" />
              </div>

              <div>
                <InputLabel for="gif" value="GIF (300x300px, max 5 secondi)" />
                <input
                  type="file"
                  id="gif"
                  accept="image/gif"
                  @change="handleGifChange"
                  class="mt-1 block w-full text-sm text-gray-500
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-md file:border-0
                    file:text-sm file:font-semibold
                    file:bg-indigo-50 file:text-indigo-700
                    hover:file:bg-indigo-100"
                />
                <InputError class="mt-2" :message="form.errors.gif" />
                <div v-if="gifPreview" class="mt-2">
                  <img :src="gifPreview" alt="Anteprima GIF" class="w-32 h-32 object-cover rounded" />
                </div>
              </div>

              <div>
                <InputLabel for="audio" value="Audio (max 30 secondi)" />
                <input
                  type="file"
                  id="audio"
                  accept="audio/mp3,audio/wav"
                  @change="handleAudioChange"
                  class="mt-1 block w-full text-sm text-gray-500
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-md file:border-0
                    file:text-sm file:font-semibold
                    file:bg-indigo-50 file:text-indigo-700
                    hover:file:bg-indigo-100"
                />
                <InputError class="mt-2" :message="form.errors.audio" />
                <div v-if="audioPreview" class="mt-2">
                  <audio controls :src="audioPreview" class="w-full"></audio>
                </div>
              </div>

              <div>
                <InputLabel value="Progetti" />
                <Multiselect
                  v-model="selectedProjects"
                  :options="projects"
                  label="name"
                  value="id"
                  placeholder="Seleziona i progetti..."
                  class="mt-1"
                  @update:modelValue="handleProjectChange"
                />
                <InputError class="mt-2" :message="form.errors.project_ids" />
              </div>

              <div class="flex items-center justify-end">
                <PrimaryButton class="ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                  Aggiorna Personaggio
                </PrimaryButton>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template> 
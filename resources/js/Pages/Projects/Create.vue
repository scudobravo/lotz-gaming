<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
  scenes: {
    type: Array,
    required: true
  },
  items: {
    type: Array,
    required: true
  }
})

const form = useForm({
  name: '',
  description: '',
  cover_image: null,
  status: 'draft',
  initial_scene_id: null,
  required_items: []
});

const coverPreview = ref(null);

const handleFileUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    console.log('File selezionato:', {
      name: file.name,
      size: file.size,
      type: file.type,
      lastModified: file.lastModified
    });
    
    // Verifica che il file sia un'immagine
    if (!file.type.startsWith('image/')) {
      alert('Il file deve essere un\'immagine');
      event.target.value = null;
      return;
    }

    // Verifica la dimensione del file (2MB = 2 * 1024 * 1024 bytes)
    const maxSize = 2 * 1024 * 1024;
    if (file.size > maxSize) {
      alert('Il file è troppo grande. La dimensione massima consentita è 2MB');
      event.target.value = null;
      return;
    }
    
    form.cover_image = file;
    coverPreview.value = URL.createObjectURL(file);
  }
};

const submit = async () => {
  try {
    const formData = new FormData();
    formData.append('name', form.name);
    formData.append('description', form.description);
    formData.append('status', form.status);
    
    // Gestione corretta di initial_scene_id
    if (form.initial_scene_id === 'null' || form.initial_scene_id === null || form.initial_scene_id === '') {
      // Non aggiungiamo il campo se è null
    } else {
      formData.append('initial_scene_id', form.initial_scene_id);
    }
    
    if (form.cover_image instanceof File) {
      console.log('Aggiungo immagine al form:', {
        name: form.cover_image.name,
        size: form.cover_image.size,
        type: form.cover_image.type
      });
      formData.append('cover_image', form.cover_image);
    }
    
    // Gestione corretta di required_items
    if (form.required_items && form.required_items.length > 0) {
      form.required_items.forEach(id => {
        if (id !== null && id !== undefined) {
          formData.append('required_items[]', id);
        }
      });
    } else {
      // Se non ci sono elementi selezionati, invia un array vuoto
      formData.append('required_items[]', '');
    }

    // Debug del FormData
    for (let [key, value] of formData.entries()) {
      console.log('FormData entry:', key, value instanceof File ? 
        `${value.name} (${value.size} bytes)` : value);
    }

    console.log('Invio richiesta...');
    const response = await axios.post(
      route('projects.store'),
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
      router.visit(route('projects.index'));
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
      if (errors.cover_image) {
        alert(`Errore immagine: ${errors.cover_image.join(', ')}`);
      }
      if (errors.required_items) {
        alert(`Errore elementi richiesti: ${errors.required_items.join(', ')}`);
      }
      if (errors.initial_scene_id) {
        alert(`Errore scena iniziale: ${errors.initial_scene_id.join(', ')}`);
      }
    } else {
      alert('Errore sconosciuto durante il caricamento');
    }
  }
};
</script>

<template>
  <Head title="Nuovo Progetto" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Nuovo Progetto</h2>
    </template>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div class="px-4 py-5 sm:px-0">
        <form @submit.prevent="submit" class="space-y-8 divide-y divide-gray-200">
          <div class="space-y-8 divide-y divide-gray-200">
            <div>
              <div>
                <h3 class="text-lg leading-6 font-medium text-gray-900">Nuovo Progetto</h3>
                <p class="mt-1 text-sm text-gray-500">Crea un nuovo progetto per il tuo gioco interattivo.</p>
              </div>

              <div class="mt-6 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <div class="sm:col-span-4">
                  <label for="name" class="block text-sm font-medium text-gray-700">Nome del Progetto</label>
                  <div class="mt-1">
                    <input type="text" name="name" id="name" v-model="form.name"
                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" />
                  </div>
                  <p v-if="form.errors.name" class="mt-2 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div class="sm:col-span-6">
                  <label for="description" class="block text-sm font-medium text-gray-700">Descrizione</label>
                  <div class="mt-1">
                    <RichTextEditor v-model="form.description" />
                  </div>
                  <p v-if="form.errors.description" class="mt-2 text-sm text-red-600">{{ form.errors.description }}</p>
                </div>

                <div class="sm:col-span-6">
                  <label for="cover_image" class="block text-sm font-medium text-gray-700">Immagine di Copertina</label>
                  <div class="mt-1 flex items-center">
                    <input type="file" name="cover_image" id="cover_image" @change="handleFileUpload"
                      class="mt-1 block w-full text-sm text-gray-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-md file:border-0
                        file:text-sm file:font-semibold
                        file:bg-indigo-50 file:text-indigo-700
                        hover:file:bg-indigo-100" />
                  </div>
                  <p v-if="form.errors.cover_image" class="mt-2 text-sm text-red-600">{{ form.errors.cover_image }}</p>
                  <div v-if="coverPreview" class="mt-2">
                    <img :src="coverPreview" alt="Anteprima copertina" class="w-32 h-32 object-cover rounded" />
                  </div>
                </div>

                <div class="sm:col-span-4">
                  <label for="status" class="block text-sm font-medium text-gray-700">Stato</label>
                  <div class="mt-1">
                    <select id="status" name="status" v-model="form.status"
                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                      <option value="draft">Bozza</option>
                      <option value="published">Pubblicato</option>
                    </select>
                  </div>
                  <p v-if="form.errors.status" class="mt-2 text-sm text-red-600">{{ form.errors.status }}</p>
                </div>

                <div class="sm:col-span-4">
                  <label for="initial_scene_id" class="block text-sm font-medium text-gray-700">Scena Iniziale</label>
                  <div class="mt-1">
                    <select id="initial_scene_id" name="initial_scene_id" v-model="form.initial_scene_id"
                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                      <option :value="null" disabled>— Nessuna scena iniziale —</option>
                      <option v-for="scene in scenes" :key="scene.id" :value="scene.id">{{ scene.title }}</option>
                    </select>
                  </div>
                  <p v-if="form.errors.initial_scene_id" class="mt-2 text-sm text-red-600">{{ form.errors.initial_scene_id }}</p>
                </div>

                <div class="sm:col-span-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Elementi Richiesti</label>
                    <div class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                      <div v-for="item in items" :key="item.id" class="relative flex items-start">
                        <div class="flex h-6 items-center">
                          <input
                            :id="'item-' + item.id"
                            :value="item.id"
                            v-model="form.required_items"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                          />
                        </div>
                        <div class="ml-3 text-sm leading-6">
                          <label :for="'item-' + item.id" class="font-medium text-gray-900">{{ item.identifier }} - {{ item.name }}</label>
                          <p class="text-gray-500">{{ item.description }}</p>
                        </div>
                      </div>
                    </div>
                    <p v-if="form.errors.required_items" class="mt-2 text-sm text-red-600">{{ form.errors.required_items }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="pt-5">
            <div class="flex justify-end">
              <Link :href="route('projects.index')"
                class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Annulla
              </Link>
              <button type="submit"
                class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Salva
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template> 
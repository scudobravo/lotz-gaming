<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { ref } from 'vue';
import axios from 'axios';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
  item: {
    type: Object,
    required: true
  }
});

const form = useForm({
  identifier: props.item.identifier,
  name: props.item.name,
  description: props.item.description,
  image: null
});

const imagePreview = ref(props.item.image_url || null);

console.log('Dati elemento:', {
  image: props.item.image,
  image_url: props.item.image_url
});

const handleImageUpload = (event) => {
  const file = event.target.files[0];
  if (!file) return;

  // Verifica il tipo di file
  if (!file.type.startsWith('image/')) {
    alert('Per favore seleziona un file immagine valido');
    return;
  }

  // Verifica la dimensione del file (max 2MB)
  if (file.size > 2 * 1024 * 1024) {
    alert('L\'immagine non può superare i 2MB');
    return;
  }

  form.image = file;
  
  // Crea un preview dell'immagine
  const reader = new FileReader();
  reader.onload = (e) => {
    imagePreview.value = e.target.result;
  };
  reader.readAsDataURL(file);
};

const submit = () => {
  const formData = new FormData();
  formData.append('_method', 'PUT');
  formData.append('identifier', form.identifier);
  formData.append('name', form.name);
  formData.append('description', form.description);
  if (form.image) {
    formData.append('image', form.image);
  }

  axios.post(route('items.update', props.item.id), formData, {
    headers: {
      'Content-Type': 'multipart/form-data'
    },
    onUploadProgress: (progressEvent) => {
      const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
      console.log('Progresso upload:', percentCompleted);
    }
  })
  .then(response => {
    if (response.data.success) {
      router.visit(route('items.index'));
    }
  })
  .catch(error => {
    console.error('Errore durante il salvataggio:', error);
    if (error.response && error.response.data.errors) {
      Object.keys(error.response.data.errors).forEach(key => {
        form.errors[key] = error.response.data.errors[key][0];
      });
    }
  });
};
</script>

<template>
  <Head title="Modifica Elemento" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifica Elemento</h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <form @submit.prevent="submit" class="space-y-6">
              <div>
                <InputLabel for="identifier" value="Identificatore" />
                <TextInput
                  id="identifier"
                  type="text"
                  class="mt-1 block w-full"
                  v-model="form.identifier"
                  required
                  autofocus
                />
                <InputError :message="form.errors.identifier" class="mt-2" />
              </div>

              <div>
                <InputLabel for="name" value="Nome" />
                <TextInput
                  id="name"
                  type="text"
                  class="mt-1 block w-full"
                  v-model="form.name"
                  required
                />
                <InputError :message="form.errors.name" class="mt-2" />
              </div>

              <div>
                <InputLabel for="description" value="Descrizione" />
                <RichTextEditor
                  id="description"
                  class="mt-1 block w-full"
                  v-model="form.description"
                  required
                />
                <InputError :message="form.errors.description" class="mt-2" />
              </div>

              <div>
                <InputLabel for="image" value="Immagine" />
                <div v-if="item.image_url" class="mt-2">
                  <img :src="item.image_url" :alt="item.name" class="h-20 w-20 object-cover rounded" />
                </div>
                <input 
                  id="image"
                  type="file" 
                  @change="handleImageUpload" 
                  accept="image/*"
                  class="mt-1 block w-full text-sm text-gray-500
                    file:mr-4 file:py-2 file:px-4
                    file:rounded-md file:border-0
                    file:text-sm file:font-semibold
                    file:bg-indigo-50 file:text-indigo-700
                    hover:file:bg-indigo-100"
                />
                <InputError :message="form.errors.image" class="mt-2" />
                
                <!-- Preview dell'immagine -->
                <div v-if="imagePreview" class="mt-4">
                  <img :src="imagePreview" alt="Preview" class="w-64 h-64 object-contain rounded-lg border border-gray-200">
                </div>
              </div>

              <div class="flex items-center justify-end">
                <Link :href="route('items.index')" class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring ring-gray-300 disabled:opacity-25 transition">
                  Annulla
                </Link>
                <PrimaryButton class="ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                  Aggiorna
                </PrimaryButton>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template> 
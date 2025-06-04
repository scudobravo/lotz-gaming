<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    scene: {
        type: Object,
        required: true
    },
    availableScenes: {
        type: Array,
        required: true
    },
    projects: {
        type: Array,
        required: true
    },
    characters: {
        type: Array,
        required: true
    }
});

const form = useForm({
    _method: 'PUT',
    title: props.scene.title,
    type: props.scene.type,
    entry_message: props.scene.entry_message,
    media_gif: null,
    media_audio: null,
    puzzle_question: props.scene.puzzle_question || '',
    correct_answer: props.scene.correct_answer || '',
    success_message: props.scene.success_message || '',
    failure_message: props.scene.failure_message || '',
    max_attempts: props.scene.max_attempts || 3,
    item_id: props.scene.item_id || '',
    character_id: props.scene.character_id || '',
    project_id: props.scene.project_id,
    choices: props.scene.choices || [],
    next_scene_id: props.scene.next_scene_id || ''
});

// Inizializza le preview con i valori esistenti
const gifPreview = ref(props.scene.media_gif_url || null);
const audioPreview = ref(props.scene.media_audio_url || null);

console.log('Scene data:', {
    media_gif: props.scene.media_gif,
    media_gif_url: props.scene.media_gif_url,
    media_audio: props.scene.media_audio,
    media_audio_url: props.scene.media_audio_url,
    full_scene: props.scene
});

const addChoice = () => {
    form.choices.push({
        label: '',
        target_scene_id: '',
        order: form.choices.length
    });
};

const removeChoice = (index) => {
    form.choices.splice(index, 1);
    // Aggiorna l'ordine delle scelte rimanenti
    form.choices.forEach((choice, idx) => {
        choice.order = idx;
    });
};

const handleGifUpload = (event) => {
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
        
        form.media_gif = file;
        gifPreview.value = URL.createObjectURL(file);
    }
};

const handleAudioUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        console.log('File audio selezionato:', {
            name: file.name,
            size: file.size,
            type: file.type,
            lastModified: file.lastModified
        });
        
        // Verifica che il file sia un audio
        if (!file.type.startsWith('audio/')) {
            alert('Il file deve essere un audio');
            event.target.value = null;
            return;
        }

        // Verifica la dimensione del file (10MB = 10 * 1024 * 1024 bytes)
        const maxSize = 10 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('Il file è troppo grande. La dimensione massima consentita è 10MB');
            event.target.value = null;
            return;
        }
        
        form.media_audio = file;
        audioPreview.value = URL.createObjectURL(file);
    }
};

const submit = async () => {
    try {
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('title', form.title);
        formData.append('entry_message', form.entry_message);
        formData.append('type', form.type);
        formData.append('order', form.order);
        formData.append('project_id', form.project_id);
        
        if (form.media_gif instanceof File) {
            console.log('Aggiungo GIF al form:', {
                name: form.media_gif.name,
                size: form.media_gif.size,
                type: form.media_gif.type
            });
            formData.append('media_gif', form.media_gif);
        }
        
        if (form.media_audio instanceof File) {
            console.log('Aggiungo audio al form:', {
                name: form.media_audio.name,
                size: form.media_audio.size,
                type: form.media_audio.type
            });
            formData.append('media_audio', form.media_audio);
        }

        // Debug del FormData
        for (let [key, value] of formData.entries()) {
            console.log('FormData entry:', key, value instanceof File ? 
                `${value.name} (${value.size} bytes)` : value);
        }

        console.log('Invio richiesta...');
        const response = await axios.post(
            route('scenes.update', props.scene.id),
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
            router.visit(route('scenes.index'));
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
            if (errors.media_gif) {
                alert(`Errore GIF: ${errors.media_gif.join(', ')}`);
            }
            if (errors.media_audio) {
                alert(`Errore audio: ${errors.media_audio.join(', ')}`);
            }
        } else {
            alert('Errore sconosciuto durante il caricamento');
        }
    }
};
</script>

<template>
    <Head title="Modifica Scena" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Modifica Scena</h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="mb-6">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Modifica Scena</h3>
                            <p class="mt-1 text-sm text-gray-500">Modifica i dettagli della scena e le sue scelte.</p>
                        </div>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <InputLabel for="title" value="Titolo" />
                                <TextInput
                                    id="title"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.title"
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.title" />
                            </div>

                            <div>
                                <InputLabel for="type" value="Tipo di Scena" />
                                <select
                                    id="type"
                                    v-model="form.type"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required
                                >
                                    <option value="intro">Intro</option>
                                    <option value="investigation">Investigation</option>
                                    <option value="puzzle">Enigma/Puzzle</option>
                                    <option value="final">Finale</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.type" />
                            </div>

                            <div>
                                <InputLabel for="entry_message" value="Messaggio Iniziale" />
                                <RichTextEditor
                                    id="entry_message"
                                    v-model="form.entry_message"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.entry_message" />
                            </div>

                            <div class="sm:col-span-6">
                                <label for="media_gif" class="block text-sm font-medium text-gray-700">GIF (max 2MB)</label>
                                <div class="mt-1 flex items-center">
                                    <input type="file" name="media_gif" id="media_gif" @change="handleGifUpload"
                                        class="mt-1 block w-full text-sm text-gray-500
                                            file:mr-4 file:py-2 file:px-4
                                            file:rounded-md file:border-0
                                            file:text-sm file:font-semibold
                                            file:bg-indigo-50 file:text-indigo-700
                                            hover:file:bg-indigo-100" />
                                </div>
                                <p v-if="form.errors.media_gif" class="mt-2 text-sm text-red-600">{{ form.errors.media_gif }}</p>
                                <div v-if="gifPreview" class="mt-2">
                                    <img :src="gifPreview" alt="Anteprima GIF" class="w-64 h-64 object-contain rounded" />
                                </div>
                            </div>

                            <div class="sm:col-span-6">
                                <label for="media_audio" class="block text-sm font-medium text-gray-700">Audio (max 10MB)</label>
                                <div class="mt-1 flex items-center">
                                    <input type="file" name="media_audio" id="media_audio" @change="handleAudioUpload"
                                        class="mt-1 block w-full text-sm text-gray-500
                                            file:mr-4 file:py-2 file:px-4
                                            file:rounded-md file:border-0
                                            file:text-sm file:font-semibold
                                            file:bg-indigo-50 file:text-indigo-700
                                            hover:file:bg-indigo-100" />
                                </div>
                                <p v-if="form.errors.media_audio" class="mt-2 text-sm text-red-600">{{ form.errors.media_audio }}</p>
                                <div v-if="audioPreview" class="mt-2">
                                    <audio controls :src="audioPreview" class="w-full"></audio>
                                </div>
                            </div>

                            <!-- Campi specifici per i puzzle -->
                            <div v-if="form.type === 'puzzle'">
                                <div>
                                    <InputLabel for="puzzle_question" value="Domanda del Puzzle" />
                                    <TextInput
                                        id="puzzle_question"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.puzzle_question"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.puzzle_question" />
                                </div>

                                <div class="mt-4">
                                    <InputLabel for="correct_answer" value="Risposta Corretta" />
                                    <TextInput
                                        id="correct_answer"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="form.correct_answer"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.correct_answer" />
                                </div>

                                <div class="mt-4">
                                    <InputLabel for="max_attempts" value="Tentativi Massimi" />
                                    <TextInput
                                        id="max_attempts"
                                        type="number"
                                        class="mt-1 block w-full"
                                        v-model="form.max_attempts"
                                        required
                                        min="1"
                                    />
                                    <InputError class="mt-2" :message="form.errors.max_attempts" />
                                </div>

                                <div class="mt-4">
                                    <InputLabel for="success_message" value="Messaggio di Successo" />
                                    <RichTextEditor
                                        id="success_message"
                                        v-model="form.success_message"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.success_message" />
                                </div>

                                <div class="mt-4">
                                    <InputLabel for="failure_message" value="Messaggio di Fallimento" />
                                    <RichTextEditor
                                        id="failure_message"
                                        v-model="form.failure_message"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.failure_message" />
                                </div>
                            </div>

                            <!-- Campi specifici per le scene di tipo investigation -->
                            <div v-if="form.type === 'investigation'" class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <h4 class="text-lg font-medium text-gray-900">Scelte</h4>
                                    <button
                                        type="button"
                                        @click="addChoice"
                                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        :disabled="form.choices.length >= 7"
                                    >
                                        Aggiungi Scelta
                                    </button>
                                </div>
                                
                                <div v-if="form.choices.length === 0" class="text-sm text-gray-500">
                                    Aggiungi fino a 7 scelte per questa scena di investigation
                                </div>

                                <div v-for="(choice, index) in form.choices" :key="index" class="p-4 border border-gray-200 rounded-md space-y-4">
                                    <div class="flex justify-between items-center">
                                        <h5 class="text-sm font-medium text-gray-900">Scelta {{ index + 1 }}</h5>
                                        <button
                                            type="button"
                                            @click="removeChoice(index)"
                                            class="text-red-600 hover:text-red-800"
                                        >
                                            Rimuovi
                                        </button>
                                    </div>

                                    <div>
                                        <InputLabel :for="'choice-label-' + index" value="Testo della Scelta" />
                                        <TextInput
                                            :id="'choice-label-' + index"
                                            type="text"
                                            class="mt-1 block w-full"
                                            v-model="choice.label"
                                            required
                                        />
                                    </div>

                                    <div>
                                        <InputLabel :for="'choice-target-' + index" value="Scena di Destinazione" />
                                        <select
                                            :id="'choice-target-' + index"
                                            v-model="choice.target_scene_id"
                                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                            required
                                        >
                                            <option value="">Seleziona una scena</option>
                                            <option 
                                                v-for="scene in availableScenes" 
                                                :key="scene.id" 
                                                :value="scene.id"
                                                :disabled="scene.id === props.scene.id"
                                            >
                                                {{ scene.title }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <InputLabel for="item_id" value="Elemento Sbloccato" />
                                <select
                                    id="item_id"
                                    v-model="form.item_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                >
                                    <option value="">Nessun elemento</option>
                                    <option v-for="item in $page.props.items" :key="item.id" :value="item.id">
                                        {{ item.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.item_id" />
                            </div>

                            <div>
                                <InputLabel for="character_id" value="Personaggio" />
                                <select
                                    id="character_id"
                                    v-model="form.character_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                >
                                    <option value="">Nessun personaggio</option>
                                    <option v-for="character in characters" :key="character.id" :value="character.id">
                                        {{ character.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.character_id" />
                            </div>

                            <div>
                                <InputLabel for="next_scene_id" value="Scena Successiva" />
                                <select
                                    id="next_scene_id"
                                    v-model="form.next_scene_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                >
                                    <option value="">Nessuna scena successiva</option>
                                    <option 
                                        v-for="scene in availableScenes" 
                                        :key="scene.id" 
                                        :value="scene.id"
                                        :disabled="scene.id === props.scene.id"
                                    >
                                        {{ scene.title }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.next_scene_id" />
                            </div>

                            <div>
                                <InputLabel for="project_id" value="Progetto" />
                                <select
                                    id="project_id"
                                    v-model="form.project_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    required
                                >
                                    <option value="">Seleziona un progetto</option>
                                    <option v-for="project in $page.props.projects" :key="project.id" :value="project.id">
                                        {{ project.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.project_id" />
                            </div>

                            <div class="flex items-center justify-end">
                                <PrimaryButton class="ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Aggiorna Scena
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 
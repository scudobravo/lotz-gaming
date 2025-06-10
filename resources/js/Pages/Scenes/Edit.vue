<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import serialize from 'form-serialize';

const props = defineProps({
    scene: {
        type: Object,
        required: true
    },
    choices: {
        type: Array,
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

onMounted(() => {
    console.log('Componente montato');
    console.log('Scene props:', props.scene);
    console.log('Choices props:', props.choices);
    console.log('Tipo scena:', props.scene.type);
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
    choices: props.choices ? props.choices.map(choice => ({
        label: choice.label,
        target_scene_id: choice.target_scene_id,
        order: choice.order
    })) : [],
    next_scene_id: props.scene.next_scene_id || ''
});

console.log('Form inizializzato con scelte:', form.choices);
console.log('Tipo scena nel form:', form.type);

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

const handleMediaUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const error = validateFile(file, 'media');
    if (error) {
        mediaError.value = error;
        form.media_gif = null;
        event.target.value = '';
        return;
    }

    mediaError.value = '';
    form.media_gif = file;
};

const handleAudioUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const error = validateFile(file, 'audio');
    if (error) {
        audioError.value = error;
        form.media_audio = null;
        event.target.value = '';
        return;
    }

    audioError.value = '';
    form.media_audio = file;
};

const validateFile = (file, type) => {
    const maxSize = 16 * 1024 * 1024; // 16MB
    const validMediaTypes = {
        media: ['video/mp4', 'image/jpeg', 'image/png'],
        audio: ['audio/mpeg', 'audio/wav']
    };

    if (file.size > maxSize) {
        return `Il file è troppo grande. Dimensione massima: 16MB`;
    }

    if (type === 'media' && !validMediaTypes.media.includes(file.type)) {
        return `Formato non supportato. Formati consentiti: MP4, JPG, JPEG, PNG`;
    }

    if (type === 'audio' && !validMediaTypes.audio.includes(file.type)) {
        return `Formato non supportato. Formati consentiti: MP3, WAV`;
    }

    return '';
};

const addChoice = () => {
    console.log('Aggiungo nuova scelta');
    const newChoice = {
        label: '',
        target_scene_id: '',
        order: form.choices.length
    };
    form.choices.push(newChoice);
    console.log('Scelte dopo aggiunta:', form.choices);
};

const removeChoice = (index) => {
    console.log('Rimuovo scelta:', index);
    form.choices.splice(index, 1);
    // Aggiorna l'ordine delle scelte rimanenti
    form.choices.forEach((choice, idx) => {
        choice.order = idx;
    });
    console.log('Scelte dopo rimozione:', form.choices);
};

const mediaError = ref('');
const audioError = ref('');

const submit = () => {
    console.log('Inizio submit con scelte:', form.choices);
    console.log('Tipo scena durante submit:', form.type);
    
    // Se il tipo è investigation, assicurati che le scelte siano valide
    if (form.type === 'investigation') {
        console.log('Tipo investigation, scelte presenti:', form.choices);
        
        // Assicurati che ogni scelta abbia i campi necessari
        form.choices = form.choices.map((choice, index) => ({
            label: choice.label || '',
            target_scene_id: choice.target_scene_id || '',
            order: index
        }));
        
        console.log('Scelte preparate per l\'invio:', form.choices);
    }

    // Usa il form di Inertia per inviare i dati
    form.post(route('scenes.update', props.scene.id), {
        preserveScroll: true,
        onSuccess: () => {
            console.log('Form inviato con successo');
            router.visit(route('scenes.index'));
        },
        onError: (errors) => {
            console.error('Errore durante l\'invio del form:', errors);
            if (errors.media_gif) {
                alert(`Errore GIF: ${errors.media_gif.join(', ')}`);
            }
            if (errors.media_audio) {
                alert(`Errore audio: ${errors.media_audio.join(', ')}`);
            }
        }
    });
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
                        <form @submit.prevent="submit" class="space-y-6" enctype="multipart/form-data">
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

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Media (GIF/Video)</label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="media_gif" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                <span>Carica un file</span>
                                                <input id="media_gif" name="media_gif" type="file" class="sr-only" @change="handleMediaUpload" accept=".mp4,.jpg,.jpeg,.png">
                                            </label>
                                            <p class="pl-1">o trascina e rilascia</p>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            MP4, JPG, JPEG o PNG fino a 16MB
                                        </p>
                                        <p v-if="form.media_gif" class="text-sm text-gray-500">
                                            File selezionato: {{ form.media_gif.name }}
                                        </p>
                                        <p v-if="mediaError" class="text-sm text-red-500">
                                            {{ mediaError }}
                                        </p>
                                        <div v-if="scene.media_url" class="mt-2">
                                            <video v-if="scene.media_url.endsWith('.mp4')" :src="scene.media_url" controls class="w-64 h-64 object-contain rounded"></video>
                                            <img v-else :src="scene.media_url" alt="Media esistente" class="w-64 h-64 object-contain rounded" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Audio</label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="media_audio" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                <span>Carica un file</span>
                                                <input id="media_audio" name="media_audio" type="file" class="sr-only" @change="handleAudioUpload" accept=".mp3,.wav">
                                            </label>
                                            <p class="pl-1">o trascina e rilascia</p>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            MP3 o WAV fino a 16MB
                                        </p>
                                        <p v-if="form.media_audio" class="text-sm text-gray-500">
                                            File selezionato: {{ form.media_audio.name }}
                                        </p>
                                        <p v-if="audioError" class="text-sm text-red-500">
                                            {{ audioError }}
                                        </p>
                                        <div v-if="scene.audio_url" class="mt-2">
                                            <audio controls :src="scene.audio_url" class="w-full"></audio>
                                        </div>
                                    </div>
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
                                            v-model="form.choices[index].label"
                                            required
                                            @input="() => console.log('Label aggiornata:', form.choices[index].label)"
                                        />
                                    </div>

                                    <div>
                                        <InputLabel :for="'choice-target-' + index" value="Scena di Destinazione" />
                                        <select
                                            :id="'choice-target-' + index"
                                            v-model="form.choices[index].target_scene_id"
                                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                            required
                                            @change="() => console.log('Target aggiornato:', form.choices[index].target_scene_id)"
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
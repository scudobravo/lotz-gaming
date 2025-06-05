<template>
  <div class="min-h-screen bg-gray-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
      <img class="mx-auto h-12 w-auto" src="/images/logo.png" alt="Logo" />
      <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
        Disclaimer - {{ project?.name }}
      </h2>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
        <div class="space-y-6">
          <div class="text-sm text-gray-600">
            <p class="mb-4">
              Benvenuto in {{ project?.name }}! Prima di iniziare, ti chiediamo di leggere attentamente questo disclaimer.
            </p>
            <p class="mb-4">
              Utilizzando questo servizio, accetti di:
            </p>
            <ul class="list-disc pl-5 mb-4">
              <li>Rispettare le regole del gioco</li>
              <li>Non utilizzare il servizio per scopi illegali</li>
              <li>Non condividere contenuti inappropriati</li>
              <li>Rispettare la privacy degli altri partecipanti</li>
            </ul>
            <p class="mb-4">
              I tuoi dati personali verranno trattati secondo la nostra <a href="https://lotz.app/admin/public/privacy-policy" target="_blank" class="text-indigo-600 hover:text-indigo-500">Privacy Policy</a>.
            </p>
          </div>

          <div>
            <button
              @click="acceptAndContinue"
              class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
              Accetta e Continua
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  project_slug: {
    type: String,
    required: true
  }
});

const project = ref(null);

onMounted(async () => {
  try {
    const response = await axios.get(`/api/projects/${props.project_slug}`);
    project.value = response.data;
  } catch (error) {
    console.error('Errore nel recupero del progetto:', error);
  }
});

const stripHtml = (html) => {
  const tmp = document.createElement('DIV');
  tmp.innerHTML = html;
  return tmp.textContent || tmp.innerText || '';
};

const acceptAndContinue = async () => {
  try {
    // Prima otteniamo i dettagli del progetto
    const response = await axios.get(`/api/projects/${props.project_slug}`);
    console.log('Risposta API:', response.data);
    const project = response.data;

    if (!project || !project.initialScene) {
      console.log('Project:', project);
      console.log('Initial Scene:', project?.initialScene);
      throw new Error('Progetto o scena iniziale non trovati');
    }

    // Poi inviamo il messaggio iniziale tramite l'API di Twilio
    const twilioResponse = await axios.post('/api/twilio/send-initial-message', {
      phone_number: 'whatsapp:+393703634676', // Il numero dell'utente
      project_id: project.id
    }, {
      headers: {
        'Accept': 'text/xml',
        'Content-Type': 'application/json'
      }
    });

    // Verifichiamo che la risposta sia un XML valido
    if (twilioResponse.data && twilioResponse.data.includes('<?xml')) {
      // Apri WhatsApp con il messaggio predefinito
      const whatsappNumber = '15074422412'; // Il tuo numero Business
      const cleanMessage = stripHtml(project.initialScene.entry_message || 'Benvenuto!');
      const message = encodeURIComponent(cleanMessage);
      const whatsappUrl = `https://api.whatsapp.com/send/?phone=${whatsappNumber}&text=${message}&type=phone_number&app_absent=0`;
      window.location.href = whatsappUrl;
    } else {
      throw new Error('Risposta non valida da Twilio');
    }
  } catch (error) {
    console.error('Errore:', error);
    alert('Si è verificato un errore. Riprova più tardi.');
  }
};
</script> 
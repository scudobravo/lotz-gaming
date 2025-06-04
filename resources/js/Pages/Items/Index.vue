<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
  items: {
    type: Object,
    required: true
  }
})
</script>

<template>
  <Head title="Elementi" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Elementi</h2>
    </template>

    <div class="py-5">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <div class="sm:flex sm:items-center">
              <div class="sm:flex-auto">
                <h1 class="text-base font-semibold text-gray-900">Elementi</h1>
                <p class="mt-2 text-sm text-gray-700">Lista di tutti gli elementi che i giocatori possono collezionare durante il gioco.</p>
              </div>
              <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
                <Link :href="route('items.create')" class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Nuovo elemento</Link>
              </div>
            </div>
            <div class="mt-8 flow-root">
              <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                  <table class="min-w-full divide-y divide-gray-300">
                    <thead>
                      <tr>
                        <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-0">Identificatore</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Nome</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Descrizione</th>
                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Immagine</th>
                        <th scope="col" class="relative py-3.5 pr-4 pl-3 sm:pr-0">
                          <span class="sr-only">Modifica</span>
                        </th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                      <tr v-if="items.data.length === 0">
                        <td colspan="5" class="py-4 text-sm text-center text-gray-500">
                          Non è stato creato ancora nessun elemento
                        </td>
                      </tr>
                      <tr v-for="item in items.data" :key="item.id">
                        <td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 sm:pl-0">{{ item.identifier }}</td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500">{{ item.name }}</td>
                        <td class="px-3 py-4 text-sm text-gray-500">{{ item.description }}</td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500">
                          <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="h-10 w-10 object-cover rounded" />
                        </td>
                        <td class="relative py-4 pr-4 pl-3 text-right text-sm font-medium whitespace-nowrap sm:pr-0">
                          <Link :href="route('items.edit', item.id)" class="text-indigo-600 hover:text-indigo-900">Modifica<span class="sr-only">, {{ item.name }}</span></Link>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template> 
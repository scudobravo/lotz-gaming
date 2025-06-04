<template>
  <div class="mt-8 flow-root">
    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
      <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
        <table class="min-w-full divide-y divide-gray-300">
          <thead>
            <tr>
              <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-0">Nome</th>
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Descrizione</th>
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Stato</th>
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Creato da</th>
              <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Data creazione</th>
              <th scope="col" class="relative py-3.5 pr-4 pl-3 sm:pr-0">
                <span class="sr-only">Modifica</span>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-if="projects.length === 0">
              <td colspan="6" class="py-4 text-sm text-center text-gray-500">
                Non è stato creato ancora nessun progetto
              </td>
            </tr>
            <tr v-for="project in projects" :key="project.id">
              <td class="py-4 pr-3 pl-4 text-sm font-medium whitespace-nowrap text-gray-900 sm:pl-0">{{ project.name }}</td>
              <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500">{{ project.description }}</td>
              <td class="px-3 py-4 text-sm whitespace-nowrap">
                <span :class="[
                  project.status === 'published' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800',
                  'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium'
                ]">
                  {{ project.status === 'published' ? 'Pubblicato' : 'Bozza' }}
                </span>
              </td>
              <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500">{{ project.creator?.name }}</td>
              <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500">{{ new Date(project.created_at).toLocaleDateString() }}</td>
              <td class="relative py-4 pr-4 pl-3 text-right text-sm font-medium whitespace-nowrap sm:pr-0">
                <Link :href="route('projects.edit', project.id)" class="text-indigo-600 hover:text-indigo-900">Modifica<span class="sr-only">, {{ project.name }}</span></Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  projects: {
    type: Array,
    required: true
  }
})

const deleteProject = (project) => {
  if (confirm('Sei sicuro di voler eliminare questo progetto?')) {
    router.delete(route('projects.destroy', project.id))
  }
}
</script> 
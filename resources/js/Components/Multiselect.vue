<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  options: {
    type: Array,
    required: true
  },
  label: {
    type: String,
    default: 'name'
  },
  value: {
    type: String,
    default: 'id'
  },
  placeholder: {
    type: String,
    default: 'Seleziona...'
  }
})

const emit = defineEmits(['update:modelValue'])

const selected = ref(props.modelValue)
const search = ref('')
const isOpen = ref(false)

const filteredOptions = computed(() => {
  return props.options.filter(option => {
    const label = option[props.label].toLowerCase()
    const searchTerm = search.value.toLowerCase()
    return label.includes(searchTerm)
  })
})

const toggleOption = (option) => {
  const index = selected.value.findIndex(item => item[props.value] === option[props.value])
  if (index === -1) {
    selected.value.push(option)
  } else {
    selected.value.splice(index, 1)
  }
  emit('update:modelValue', selected.value.map(item => item[props.value]))
}

const removeOption = (option) => {
  const index = selected.value.findIndex(item => item[props.value] === option[props.value])
  if (index !== -1) {
    selected.value.splice(index, 1)
    emit('update:modelValue', selected.value.map(item => item[props.value]))
  }
}

watch(() => props.modelValue, (newValue) => {
  selected.value = props.options.filter(option => 
    newValue.includes(option[props.value])
  )
})

const dropdownRef = ref(null)

const handleClickOutside = (event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <div class="relative" ref="dropdownRef">
    <div
      @click="isOpen = !isOpen"
      class="min-h-[38px] w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-left shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm"
    >
      <div class="flex flex-wrap gap-2">
        <div
          v-for="option in selected"
          :key="option[value]"
          class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800"
        >
          {{ option[label] }}
          <button
            type="button"
            @click.stop="removeOption(option)"
            class="ml-1 inline-flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full text-indigo-400 hover:bg-indigo-200 hover:text-indigo-500 focus:bg-indigo-500 focus:text-white focus:outline-none"
          >
            <span class="sr-only">Rimuovi {{ option[label] }}</span>
            <svg class="h-2 w-2" stroke="currentColor" fill="none" viewBox="0 0 8 8">
              <path stroke-linecap="round" stroke-width="1.5" d="M1 1l6 6m0-6L1 7" />
            </svg>
          </button>
        </div>
        <input
          v-model="search"
          type="text"
          :placeholder="selected.length === 0 ? placeholder : ''"
          class="flex-1 border-0 bg-transparent p-0 text-sm text-gray-900 placeholder-gray-500 focus:ring-0"
          @click.stop
        />
      </div>
    </div>

    <div
      v-if="isOpen"
      class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
    >
      <div
        v-for="option in filteredOptions"
        :key="option[value]"
        class="relative cursor-pointer select-none py-2 pl-3 pr-9 hover:bg-indigo-50"
        @click="toggleOption(option)"
      >
        <div class="flex items-center">
          <span
            :class="[
              selected.find(item => item[value] === option[value]) ? 'font-semibold' : 'font-normal',
              'block truncate'
            ]"
          >
            {{ option[label] }}
          </span>
        </div>
        <span
          v-if="selected.find(item => item[value] === option[value])"
          class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600"
        >
          <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path
              fill-rule="evenodd"
              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
              clip-rule="evenodd"
            />
          </svg>
        </span>
      </div>
    </div>
  </div>
</template> 
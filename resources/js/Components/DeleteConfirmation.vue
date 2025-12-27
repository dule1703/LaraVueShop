<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  itemName: {
    type: String,
    required: true
  },
  itemType: {
    type: String,
    default: 'stavku'
  },
  deleteUrl: {
    type: String,
    required: true
  }
});

const open = ref(false);

const confirmDelete = () => {
  router.delete(props.deleteUrl, {
    onSuccess: () => {
      open.value = false;
    }
  });
};
</script>

<template>
  <!-- Trigger dugme -->
  <button @click="open = true" class="text-red-600 hover:text-red-900">
    Obriši
  </button>

  <!-- Modal backdrop -->
  <div v-if="open" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50" @click="open = false"></div>

  <!-- Modal panel -->
  <div v-if="open" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex min-h-full items-center justify-center p-4 text-center">
      <div class="w-full max-w-xl transform overflow-hidden rounded-lg bg-white p-6 text-left align-middle shadow-xl transition-all">
        <h3 class="text-lg font-medium leading-6 text-gray-900">
          Da li ste sigurni?
        </h3>
        <div class="mt-2">
          <p class="text-sm text-gray-500">
            Ova akcija je nepovratna. Da li želite da trajno obrišete {{ itemType }}?
            <span class="font-semibold">{{ itemName }}</span>            
          </p>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <button
            @click="open = false"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            Otkaži
          </button>
          <button
            @click="confirmDelete"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
          >
            Da, obriši
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
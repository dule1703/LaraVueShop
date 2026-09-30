<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
  itemName: {
    type: String,
    required: true
  },
  itemType: {
    type: String,
    default: 'item'
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
  <button @click="open = true" class="font-medium text-red-600 hover:text-red-700">
    Obriši
  </button>

  <!-- Modal backdrop -->
  <div v-if="open" class="fixed inset-0 bg-black/50 transition-opacity z-50" @click="open = false"></div>

  <!-- Modal panel -->
  <div v-if="open" class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex min-h-full items-center justify-center p-4 text-center">
      <div class="w-full max-w-xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
        <h3 class="text-lg font-medium leading-6 text-brand-text-primary">
          Da li ste sigurni?
        </h3>
        <div class="mt-2">
          <p class="text-sm text-brand-text-secondary">
            Ova radnja je nepovratna.<br>
            Da li želite trajno da obrišete
            <span class="font-semibold text-brand-text-primary">{{ itemName }}</span>?
          </p>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <SecondaryButton @click="open = false">
            Otkaži
          </SecondaryButton>
          <DangerButton @click="confirmDelete">
            Da, obriši
          </DangerButton>
        </div>
      </div>
    </div>
  </div>
</template>
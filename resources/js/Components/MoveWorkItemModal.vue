<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import TreeOption from '@/Components/TreeOption.vue'; // ✅ นำเข้า Component ที่แยกออกมา

const props = defineProps({
    show: Boolean,
    item: Object,
});

const emit = defineEmits(['close', 'success']);

// State
const treeData = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');

// Form
const form = useForm({
    parent_id: null
});

// Fetch Data
const fetchTree = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(route('api.strategies.tree'));
        treeData.value = response.data;
    } catch (error) {
        console.error("Failed to load tree:", error);
    } finally {
        isLoading.value = false;
    }
};

// Actions
const selectParent = (node) => {
    if (node && node.id === props.item.id) return;
    form.parent_id = node ? node.id : null;
};

const submitMove = () => {
    if (!props.item) return;

    form.put(route('work-items.move', props.item.id), {
        onSuccess: () => {
            emit('success');
            emit('close');
            form.reset();
        },
    });
};

watch(() => props.show, (newVal) => {
    if (newVal) {
        fetchTree();
        form.parent_id = props.item?.parent_id || null;
    }
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[200] overflow-y-auto">

        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="$emit('close')"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg flex flex-col max-h-[85vh]">

                <div class="bg-purple-50 px-4 py-3 sm:px-6 border-b border-purple-100 flex justify-between items-center">
                    <h3 class="text-base font-semibold leading-6 text-purple-900">
                        🔄 ย้ายงาน: <span class="text-purple-600">{{ item?.name }}</span>
                    </h3>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-500">&times;</button>
                </div>

                <div class="px-4 py-4 sm:px-6 flex-1 overflow-y-auto min-h-[300px]">

                    <div class="mb-4">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="🔍 ค้นหาปลายทาง..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"
                        >
                    </div>

                    <div
                        class="flex items-center p-2 rounded-lg cursor-pointer mb-2 border border-dashed border-gray-300 hover:bg-gray-50 hover:border-purple-300 transition-colors"
                        :class="{'bg-purple-50 border-purple-500 text-purple-700': form.parent_id === null}"
                        @click="form.parent_id = null"
                    >
                        <span class="mr-2">🏁</span>
                        <span class="text-sm font-bold">ตั้งเป็นยุทธศาสตร์หลัก (Root)</span>
                        <span v-if="form.parent_id === null" class="ml-auto text-purple-600 font-bold">✓</span>
                    </div>

                    <div v-if="isLoading" class="text-center py-10 text-gray-400">
                        กำลังโหลดโครงสร้าง...
                    </div>

                    <div v-else class="space-y-1">
                        <TreeOption
                            :nodes="treeData"
                            :level="0"
                            :currentItemId="item?.id"
                            :selectedId="form.parent_id"
                            :search="searchQuery"
                            @select="selectParent"
                        />
                    </div>
                </div>

                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 border-t border-gray-100">
                    <button
                        type="button"
                        class="inline-flex w-full justify-center rounded-md bg-[#7A2F8F] px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-700 sm:ml-3 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="form.processing"
                        @click="submitMove"
                    >
                        <span v-if="form.processing">กำลังบันทึก...</span>
                        <span v-else>ยืนยันการย้าย</span>
                    </button>
                    <button
                        type="button"
                        class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
                        @click="$emit('close')"
                    >
                        ยกเลิก
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import axios from 'axios';

const props = defineProps({
    modelValue: String, // รับค่าชื่อเข้ามา (v-model)
    placeholder: { type: String, default: 'พิมพ์ชื่อผู้ดูแล...' }
});

const emit = defineEmits(['update:modelValue']);

const query = ref(props.modelValue || '');
const suggestions = ref([]);
const showSuggestions = ref(false);

// โหลดรายชื่อเมื่อพิมพ์
const fetchSuggestions = debounce(async (val) => {
    if (!val) {
        suggestions.value = [];
        return;
    }
    try {
        const res = await axios.get(route('api.pm.search'), { params: { query: val } });
        suggestions.value = res.data;
        showSuggestions.value = true;
    } catch (e) {
        console.error(e);
    }
}, 300);

watch(query, (val) => {
    emit('update:modelValue', val); // ส่งค่ากลับไปที่ Parent ทันทีที่พิมพ์
    fetchSuggestions(val);
});

const selectSuggestion = (name) => {
    query.value = name;
    showSuggestions.value = false;
};

// ปิด Dropdown เมื่อคลิกข้างนอก (ใช้แบบง่ายๆ หรือจะใช้ directive click-outside ก็ได้)
const closeSuggestions = () => {
    setTimeout(() => showSuggestions.value = false, 200);
};
</script>

<template>
    <div class="relative">
        <input
            type="text"
            v-model="query"
            @focus="fetchSuggestions(query)"
            @blur="closeSuggestions"
            class="w-full rounded-lg border-gray-300 focus:ring-[#4A148C] focus:border-[#4A148C] text-sm"
            :placeholder="placeholder"
        >

        <ul v-if="showSuggestions && suggestions.length > 0" class="absolute z-50 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto">
            <li v-for="item in suggestions" :key="item.id"
                @click="selectSuggestion(item.name)"
                class="px-4 py-2 hover:bg-purple-50 cursor-pointer text-sm text-gray-700">
                {{ item.name }}
            </li>
        </ul>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import axios from 'axios';

const props = defineProps({
    modelValue: String, // รับชื่อมาแสดงผล (v-model)
    placeholder: { type: String, default: 'พิมพ์ชื่อผู้ดูแล (PM)...' }
});

// ✅ เพิ่ม 'update:id' เพื่อส่ง ID กลับไป
const emit = defineEmits(['update:modelValue', 'update:id']);

const query = ref(props.modelValue || '');
const suggestions = ref([]);
const showSuggestions = ref(false);
const isLoading = ref(false);

// Sync ค่าจาก Parent (กรณีโหลดข้อมูลมาใส่ Form)
watch(() => props.modelValue, (newVal) => {
    if (newVal !== query.value) {
        query.value = newVal;
    }
});

// โหลดรายชื่อจาก Users Table
const fetchSuggestions = debounce(async (val) => {
    if (!val || val.length < 2) { // พิมพ์อย่างน้อย 2 ตัวถึงค้นหา
        suggestions.value = [];
        return;
    }

    isLoading.value = true;
    try {
        // ยิงไปที่ Controller ใหม่ (WorkItemController@searchProjectManagers)
        // ตรวจสอบใน web.php ว่า route ชื่อ 'api.pm.search' ชี้ไปที่ Controller ใหม่แล้วหรือยัง
        const res = await axios.get(route('api.pm.search'), { params: { query: val } });
        suggestions.value = res.data;
        showSuggestions.value = true;
    } catch (e) {
        console.error("Search Error:", e);
    } finally {
        isLoading.value = false;
    }
}, 300);

const onInput = (e) => {
    const val = e.target.value;
    query.value = val;

    emit('update:modelValue', val); // ส่งชื่อไปแสดงผล
    emit('update:id', null);        // ⚠️ Clear ID ทิ้งทันทีที่พิมพ์ (บังคับเลือกใหม่)

    fetchSuggestions(val);
};

const selectSuggestion = (user) => {
    query.value = user.name;
    showSuggestions.value = false;

    // ✅ ส่งค่ากลับ 2 ทาง: ชื่อ และ ID
    emit('update:modelValue', user.name);
    emit('update:id', user.id);
};

// ปิด Dropdown เมื่อคลิกข้างนอก
const closeSuggestions = () => {
    setTimeout(() => showSuggestions.value = false, 200);
};
</script>

<template>
    <div class="relative w-full">
        <div class="relative">
            <input
                type="text"
                :value="query"
                @input="onInput"
                @focus="fetchSuggestions(query)"
                @blur="closeSuggestions"
                class="w-full rounded-lg border-gray-300 focus:ring-[#7A2F8F] focus:border-[#7A2F8F] text-sm pl-10"
                :placeholder="placeholder"
                autocomplete="off"
            >
            <div class="absolute left-3 top-2.5 text-gray-400">
                <svg v-if="!isLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <svg v-else class="w-4 h-4 animate-spin text-[#7A2F8F]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </div>
        </div>

        <ul v-if="showSuggestions && suggestions.length > 0" class="absolute z-50 w-full bg-white border border-gray-200 rounded-xl shadow-xl mt-1 max-h-60 overflow-y-auto custom-scrollbar">
            <li v-for="user in suggestions" :key="user.id"
                @mousedown.prevent="selectSuggestion(user)"
                class="px-4 py-3 hover:bg-purple-50 cursor-pointer text-sm text-gray-700 border-b border-gray-50 last:border-0 flex flex-col gap-0.5 group transition">

                <span class="font-bold group-hover:text-[#7A2F8F]">{{ user.name }}</span>

                <span v-if="user.role || user.position" class="text-[10px] text-gray-400">
                    {{ user.position ? user.position : '' }}
                    <span v-if="user.position && user.role">•</span>
                    {{ user.role ? user.role.toUpperCase() : '' }}
                </span>
            </li>
        </ul>

        <div v-if="showSuggestions && suggestions.length === 0 && query.length >= 2 && !isLoading"
             class="absolute z-50 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 p-3 text-center text-xs text-gray-400 italic">
            ไม่พบรายชื่อผู้ใช้งานนี้
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>

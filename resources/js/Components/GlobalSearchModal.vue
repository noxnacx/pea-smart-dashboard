<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import throttle from 'lodash/throttle';

const props = defineProps({
    show: Boolean
});

const emit = defineEmits(['close']);

const searchInput = ref(null);
const query = ref('');
const results = ref([]);
const loading = ref(false);

watch(() => props.show, (val) => {
    if (val) {
        nextTick(() => searchInput.value?.focus());
    } else {
        query.value = '';
        results.value = [];
    }
});

const performSearch = throttle(async () => {
    if (!query.value) {
        results.value = [];
        return;
    }
    loading.value = true;
    try {
        const { data } = await axios.get(route('global.search'), { params: { q: query.value } });
        results.value = data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}, 300);

watch(query, performSearch);

const close = () => emit('close');

// ✨ ฟังก์ชันนำทางที่ฉลาดขึ้น
const navigateTo = (item) => {
    if (!item.url) return;

    if (item.type === 'download') {
        // ถ้าเป็นไฟล์ ให้เปิดลิงก์ตรงๆ (Browser จะจัดการโหลดให้)
        window.open(item.url, '_blank');
    } else {
        // ถ้าเป็นหน้าเว็บในระบบ ใช้ Inertia Router (ไม่รีเฟรชหน้า)
        router.visit(item.url);
        close(); // ปิด Modal ทันที
    }
};

const onKeydown = (e) => {
    if (e.key === 'Escape' && props.show) close();
};
onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[999] overflow-y-auto px-4 py-6 sm:px-0 flex items-start justify-center">
            <div class="fixed inset-0 transform transition-all" @click="close">
                <div class="absolute inset-0 bg-gray-900 opacity-60 backdrop-blur-sm"></div>
            </div>

            <div class="mb-6 bg-white rounded-2xl overflow-hidden shadow-2xl transform transition-all sm:w-full sm:max-w-2xl mt-16 ring-1 ring-black ring-opacity-5 relative z-10">

                <div class="relative flex items-center border-b border-gray-100 p-4">
                    <svg class="w-6 h-6 text-[#4A148C] pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input
                        ref="searchInput"
                        v-model="query"
                        type="text"
                        class="w-full border-0 focus:ring-0 text-lg text-gray-800 placeholder-gray-400 ml-3 bg-transparent h-12 outline-none"
                        placeholder="ค้นหาโครงการ, ปัญหา, ไฟล์ หรือชื่อคน..."
                    />
                    <button @click="close" class="bg-gray-100 text-gray-500 hover:text-gray-700 px-2 py-1 rounded text-xs font-bold uppercase tracking-wider">ESC</button>
                </div>

                <div class="min-h-[300px] max-h-[60vh] overflow-y-auto bg-gray-50/50 p-4">

                    <div v-if="loading" class="flex flex-col items-center justify-center h-40 text-gray-400">
                        <svg class="animate-spin h-8 w-8 text-[#4A148C] mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>กำลังค้นหาข้อมูล...</span>
                    </div>

                    <div v-else-if="!query" class="flex flex-col items-center justify-center h-full text-center py-8">
                        <img src="/images/mascot.png" alt="PEA Mascot" class="w-32 h-auto mb-4 drop-shadow-lg animate-bounce-slow">
                        <h3 class="text-lg font-bold text-[#4A148C]">สวัสดีครับ! วันนี้ให้พี่วัตช่วยหาอะไรดี?</h3>
                        <p class="text-sm text-gray-500 mt-1">พิมพ์คำค้นหาเพื่อเริ่มใช้งานได้เลยครับ</p>
                    </div>

                    <div v-else-if="results.length === 0" class="flex flex-col items-center justify-center h-full text-center py-8">
                        <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center mb-4 text-3xl">🤔</div>
                        <h3 class="text-gray-900 font-bold">ไม่พบข้อมูลที่ค้นหา</h3>
                        <p class="text-sm text-gray-500 mt-1">ลองใช้คำค้นหาอื่นดูนะครับ</p>
                    </div>

                    <div v-else class="space-y-2">
                        <div v-for="item in results" :key="item.id + item.category"
                             @click="navigateTo(item)"
                             class="flex items-center p-3 bg-white hover:bg-purple-50 rounded-xl cursor-pointer border border-gray-100 hover:border-[#4A148C] transition group shadow-sm">

                            <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 mr-4"
                                 :class="{
                                     'bg-blue-100 text-blue-600': item.category === 'Projects & Tasks',
                                     'bg-red-100 text-red-600': item.category === 'Issues & Risks',
                                     'bg-green-100 text-green-600': item.category === 'People',
                                     'bg-gray-100 text-gray-600': item.category === 'Files'
                                 }">
                                <span v-if="item.category === 'Projects & Tasks'">📂</span>
                                <span v-else-if="item.category === 'Issues & Risks'">🔥</span>
                                <span v-else-if="item.category === 'People'">👤</span>
                                <span v-else>📄</span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-800 truncate group-hover:text-[#4A148C]">{{ item.name }}</p>
                                <p class="text-xs text-gray-500 uppercase tracking-wide">{{ item.category }}</p>
                            </div>

                            <svg v-if="item.type === 'download'" class="w-5 h-5 text-gray-400 group-hover:text-[#4A148C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <svg v-else class="w-5 h-5 text-gray-300 group-hover:text-[#4A148C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </div>

                </div>

                <div class="bg-gray-50 px-4 py-3 border-t border-gray-100 flex justify-between items-center text-xs text-gray-400">
                    <span>💡 <b>Tip:</b> กด Enter เพื่อเลือกรายการแรกทันที</span>
                    <span class="font-mono">PEA Smart Search</span>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.animate-bounce-slow { animation: bounce 3s infinite; }
@keyframes bounce { 0%, 100% { transform: translateY(-5%); } 50% { transform: translateY(0); } }
</style>

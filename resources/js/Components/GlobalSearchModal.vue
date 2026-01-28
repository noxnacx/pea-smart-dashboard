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

const navigateTo = (url, type = null) => {
    if (!url) return;
    if (type === 'download') {
        window.open(url, '_blank');
    } else {
        router.visit(url);
        close();
    }
};

const onKeydown = (e) => {
    if (e.key === 'Escape' && props.show) close();
};
onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

const statusColor = (s) => ({ completed: 'text-green-600 bg-green-50', delayed: 'text-red-600 bg-red-50', pending: 'text-gray-600 bg-gray-50', in_progress: 'text-blue-600 bg-blue-50' }[s] || 'text-gray-600');
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[999] overflow-y-auto px-4 py-6 sm:px-0 flex items-start justify-center">

            <div class="fixed inset-0 transform transition-all" @click="close">
                <div class="absolute inset-0 bg-gray-900 opacity-60 backdrop-blur-sm"></div>
            </div>

            <div class="mb-6 bg-white rounded-2xl overflow-hidden shadow-2xl transform transition-all w-full max-w-3xl mt-16 ring-1 ring-black ring-opacity-5 relative z-10">

                <div class="relative flex items-center border-b border-gray-100 p-4">
                    <svg class="w-6 h-6 text-[#4A148C] pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input
                        ref="searchInput"
                        v-model="query"
                        type="text"
                        class="w-full border-0 focus:ring-0 text-lg text-gray-800 placeholder-gray-400 ml-3 bg-transparent h-12 outline-none"
                        placeholder="ค้นหาโครงการ, กอง, แผนก หรือชื่อ PM..."
                    />
                    <button @click="close" class="bg-gray-100 text-gray-500 hover:text-gray-700 px-2 py-1 rounded text-xs font-bold uppercase tracking-wider">ESC</button>
                </div>

                <div class="min-h-[300px] max-h-[70vh] overflow-y-auto bg-gray-50/50 p-4">

                    <div v-if="loading" class="flex flex-col items-center justify-center h-40 text-gray-400">
                        <svg class="animate-spin h-8 w-8 text-[#4A148C] mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>กำลังค้นหา...</span>
                    </div>

                    <div v-else-if="!query" class="flex flex-col items-center justify-center h-full text-center py-8">
                        <img src="/images/mascot.png" alt="PEA Mascot" class="w-32 h-auto mb-4 drop-shadow-lg animate-bounce-slow">
                        <h3 class="text-lg font-bold text-[#4A148C]">สวัสดีครับ! วันนี้ให้พี่วัตช่วยหาอะไรดี?</h3>
                        <p class="text-sm text-gray-500 mt-1">พิมพ์คำค้นหาเพื่อเริ่มใช้งานได้เลยครับ</p>
                    </div>

                    <div v-else-if="results.length === 0" class="flex flex-col items-center justify-center h-full text-center py-8">
                        <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center mb-4 text-3xl">🤔</div>
                        <h3 class="text-gray-900 font-bold">ไม่พบข้อมูล</h3>
                        <p class="text-sm text-gray-500 mt-1">ลองใช้คำค้นหาอื่นดูนะครับ</p>
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="(item, index) in results" :key="index"
                             class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden hover:border-[#4A148C] hover:shadow-md transition group">

                            <div v-if="item.type === 'pm'" class="p-4">
                                <div class="flex items-center gap-3 cursor-pointer" @click="navigateTo(item.url)">
                                    <div class="w-12 h-12 rounded-full bg-[#4A148C] text-white flex items-center justify-center font-bold text-xl shrink-0 border-2 border-purple-200">
                                        {{ item.name.charAt(0) }}
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-bold text-gray-800 group-hover:text-[#4A148C] text-lg">{{ item.name }}</h4>
                                        <p class="text-xs text-gray-500">Project Manager • คลิกเพื่อดูโปรไฟล์</p>
                                    </div>
                                    <svg class="w-5 h-5 text-gray-300 group-hover:text-[#4A148C]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </div>

                                <div v-if="item.related_projects && item.related_projects.length > 0" class="mt-3 pl-16 space-y-2 border-l-2 border-purple-50 ml-6">
                                    <div class="text-[10px] text-gray-400 uppercase font-bold tracking-wider pl-2">โครงการที่ดูแล:</div>
                                    <div v-for="proj in item.related_projects" :key="proj.url"
                                         @click.stop="navigateTo(proj.url)"
                                         class="pl-2 text-sm text-gray-600 hover:text-[#4A148C] hover:underline cursor-pointer truncate flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 bg-purple-200 rounded-full"></span>
                                        {{ proj.name }}
                                    </div>
                                </div>
                            </div>

                            <div v-else @click="navigateTo(item.url, item.type)" class="flex items-center p-3 cursor-pointer">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 mr-4"
                                     :class="{
                                         'bg-blue-100 text-blue-600': item.category === 'Projects & Plans',
                                         'bg-red-100 text-red-600': item.category === 'Issues & Risks',
                                         'bg-gray-100 text-gray-600': item.category === 'Files'
                                     }">
                                    <span v-if="item.category === 'Projects & Plans'">📂</span>
                                    <span v-else-if="item.category === 'Issues & Risks'">🔥</span>
                                    <span v-else>📄</span>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start">
                                        <p class="text-sm font-bold text-gray-800 truncate group-hover:text-[#4A148C] pr-2">{{ item.name }}</p>
                                        <span v-if="item.type==='work_item'" class="text-[10px] px-2 py-0.5 rounded font-bold uppercase whitespace-nowrap" :class="statusColor(item.status)">{{ item.status }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 flex items-center gap-2 mt-0.5">
                                        <span class="uppercase tracking-wide font-semibold">{{ item.category }}</span>
                                        <span v-if="item.description" class="text-gray-400 truncate max-w-[200px]"> • {{ item.description }}</span>
                                    </p>
                                </div>

                                <svg v-if="item.type === 'download'" class="w-5 h-5 text-gray-400 group-hover:text-[#4A148C] ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                <svg v-else class="w-5 h-5 text-gray-300 group-hover:text-[#4A148C] ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>

                        </div>
                    </div>

                </div>

                <div class="bg-gray-50 px-4 py-3 border-t border-gray-100 flex justify-between items-center text-xs text-gray-400">
                    <span>💡 <b>Tip:</b> กด Enter เพื่อเลือกรายการแรกทันที</span>
                    <span class="font-mono text-[#4A148C]">PEA Smart Search</span>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.animate-bounce-slow { animation: bounce 3s infinite; }
@keyframes bounce { 0%, 100% { transform: translateY(-5%); } 50% { transform: translateY(0); } }
</style>

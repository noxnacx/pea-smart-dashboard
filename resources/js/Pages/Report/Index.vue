<script setup>
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    divisions: Array,
    strategies: Array,
    projects: Array // ✅ รับรายชื่อโครงการมาให้เลือก
});

const activeModal = ref(null);

const progressForm = ref({ strategy_id: '', division_id: '' });
const issueForm = ref({ type: '', status: '', start_date: '', end_date: '' });
const executiveForm = ref({ project_ids: [] }); // ✅ เปลี่ยนเป็น Array ของโปรเจค
const treeForm = ref({ strategy_id: '' });

// ฟังก์ชันค้นหาโครงการใน Modal ให้หาง่ายขึ้น
const projectSearch = ref('');
const filteredProjects = computed(() => {
    if (!projectSearch.value) return props.projects;
    return props.projects.filter(p => p.name.toLowerCase().includes(projectSearch.value.toLowerCase()));
});

const openModal = (type) => { activeModal.value = type; };
const closeModal = () => { activeModal.value = null; };

// ฟังก์ชันเปิด URL และจัดการ Array Parameters (เช่น project_ids[]=1&project_ids[]=2)
const downloadReport = (baseRoute, formParams) => {
    let url = route(baseRoute);
    const params = new URLSearchParams();

    for (const key in formParams) {
        if (Array.isArray(formParams[key])) {
            if (formParams[key].length > 0) {
                formParams[key].forEach(val => params.append(`${key}[]`, val));
            }
        } else if (formParams[key]) {
            params.append(key, formParams[key]);
        }
    }

    if(params.toString()) {
        url += '?' + params.toString();
    }

    window.open(url, '_blank');
    closeModal();
};
</script>

<template>
    <Head title="ระบบรายงาน" />

    <PeaSidebarLayout>
        <div class="py-12 relative">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">

                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-[#4A148C]">ระบบรายงาน (Reporting Center)</h2>
                    <p class="text-gray-500 mt-2">เลือกประเภทและตั้งค่าข้อมูลรายงานที่ต้องการดาวน์โหลด</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">

                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <div class="h-2 bg-[#4A148C]"></div>
                        <div class="p-6">
                            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-6 text-[#4A148C]">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-2">รายงานความก้าวหน้า</h3>
                            <p class="text-sm text-gray-500 mb-6 h-10 line-clamp-2">สรุปสถานะ แผนงาน โครงการ และ % ความคืบหน้าของงานทั้งหมด</p>
                            <button @click="openModal('progress')" class="w-full py-2.5 bg-gray-50 text-gray-800 rounded-lg hover:bg-[#4A148C] hover:text-white transition font-bold border border-gray-200">
                                ดาวน์โหลด
                            </button>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <div class="h-2 bg-[#FDB913]"></div>
                        <div class="p-6">
                            <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center mb-6 text-orange-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-2">รายงานปัญหา/ความเสี่ยง</h3>
                            <p class="text-sm text-gray-500 mb-6 h-10 line-clamp-2">รายการ Issue Log และความเสี่ยงที่ยังไม่ได้รับการแก้ไข</p>
                            <button @click="openModal('issues')" class="w-full py-2.5 bg-gray-50 text-gray-800 rounded-lg hover:bg-[#FDB913] transition font-bold border border-gray-200">
                                ดาวน์โหลด
                            </button>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <div class="h-2 bg-blue-600"></div>
                        <div class="p-6">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-6 text-blue-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-2">รายงานสรุปผู้บริหาร</h3>
                            <p class="text-sm text-gray-500 mb-6 h-10 line-clamp-2">สรุปภาพรวม KPI งบประมาณ และสถานะโครงการสำคัญ</p>
                            <button @click="openModal('executive')" class="w-full py-2.5 bg-gray-50 text-gray-800 rounded-lg hover:bg-blue-600 hover:text-white transition font-bold border border-gray-200">
                                ดาวน์โหลด
                            </button>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <div class="h-2 bg-teal-500"></div>
                        <div class="p-6">
                            <div class="w-12 h-12 bg-teal-50 rounded-xl flex items-center justify-center mb-6 text-teal-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-2">รายงานโครงสร้างองค์กร</h3>
                            <p class="text-sm text-gray-500 mb-6 h-10 line-clamp-2">แผนผังยุทธศาสตร์ แผนงาน และโครงการแบบลำดับชั้น</p>
                            <button @click="openModal('tree')" class="w-full py-2.5 bg-gray-50 text-gray-800 rounded-lg hover:bg-teal-500 hover:text-white transition font-bold border border-gray-200">
                                ดาวน์โหลด
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <Teleport to="body">
                <div v-if="activeModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in flex flex-col max-h-[90vh]">

                        <div class="px-6 py-4 flex justify-between items-center border-b border-gray-100 bg-gray-50 shrink-0">
                            <h3 class="text-lg font-bold text-[#4A148C]">⚙️ ตั้งค่าการดาวน์โหลด</h3>
                            <button @click="closeModal" class="text-gray-400 hover:text-red-500 text-2xl leading-none">&times;</button>
                        </div>

                        <div class="p-6 space-y-4 overflow-y-auto custom-scrollbar flex-1">
                            <div v-if="activeModal === 'progress'" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">เลือกยุทธศาสตร์ (Strategy)</label>
                                    <select v-model="progressForm.strategy_id" class="w-full rounded-lg border-gray-300 focus:border-[#4A148C] focus:ring-[#4A148C]">
                                        <option value="">ทั้งหมด (All)</option>
                                        <option v-for="st in strategies" :key="st.id" :value="st.id">{{ st.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">เลือกกอง (Division)</label>
                                    <select v-model="progressForm.division_id" class="w-full rounded-lg border-gray-300 focus:border-[#4A148C] focus:ring-[#4A148C]">
                                        <option value="">ทั้งหมด (All)</option>
                                        <option v-for="div in divisions" :key="div.id" :value="div.id">{{ div.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="activeModal === 'issues'" class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">ประเภท</label>
                                        <select v-model="issueForm.type" class="w-full rounded-lg border-gray-300 focus:border-[#FDB913] focus:ring-[#FDB913]">
                                            <option value="">ทั้งหมด</option>
                                            <option value="issue">ปัญหา (Issue)</option>
                                            <option value="risk">ความเสี่ยง (Risk)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">สถานะ</label>
                                        <select v-model="issueForm.status" class="w-full rounded-lg border-gray-300 focus:border-[#FDB913] focus:ring-[#FDB913]">
                                            <option value="">ทั้งหมด</option>
                                            <option value="open">รอแก้ไข (Open)</option>
                                            <option value="in_progress">กำลังดำเนินการ (In Progress)</option>
                                            <option value="resolved">แก้ไขแล้ว (Resolved)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 border-t border-gray-100 pt-4">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">ตั้งแต่วันที่</label>
                                        <input type="date" v-model="issueForm.start_date" class="w-full rounded-lg border-gray-300 focus:border-[#FDB913] focus:ring-[#FDB913]">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">ถึงวันที่</label>
                                        <input type="date" v-model="issueForm.end_date" class="w-full rounded-lg border-gray-300 focus:border-[#FDB913] focus:ring-[#FDB913]">
                                    </div>
                                </div>
                            </div>

                            <div v-if="activeModal === 'executive'">
                                <label class="block text-sm font-bold text-gray-700 mb-1">เลือกโครงการที่ต้องการนำเสนอ (เลือกได้หลายรายการ)</label>
                                <input type="text" v-model="projectSearch" placeholder="🔍 ค้นหาชื่อโครงการ..." class="w-full rounded-lg border-gray-300 focus:border-blue-500 mb-2 text-sm">

                                <div class="max-h-60 overflow-y-auto border border-gray-300 rounded-lg p-2 space-y-1 bg-gray-50 custom-scrollbar">
                                    <label v-for="pj in filteredProjects" :key="pj.id" class="flex items-start gap-2 p-2 hover:bg-white rounded cursor-pointer transition border border-transparent hover:border-gray-200">
                                        <input type="checkbox" v-model="executiveForm.project_ids" :value="pj.id" class="rounded text-blue-600 focus:ring-blue-500 mt-0.5">
                                        <span class="text-sm text-gray-700 leading-tight">{{ pj.name }}</span>
                                    </label>
                                    <div v-if="filteredProjects.length === 0" class="text-center text-gray-400 py-4 text-sm">ไม่พบโครงการ</div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">* หากไม่เลือกใดๆ ระบบจะดึงข้อมูลโครงการที่ใช้งบประมาณสูงสุด 5 อันดับแรกมาแสดง</p>
                            </div>

                            <div v-if="activeModal === 'tree'">
                                <label class="block text-sm font-bold text-gray-700 mb-1">เลือกยุทธศาสตร์</label>
                                <select v-model="treeForm.strategy_id" class="w-full rounded-lg border-gray-300 focus:border-teal-500 focus:ring-teal-500">
                                    <option value="">ทั้งหมด (All)</option>
                                    <option v-for="st in strategies" :key="st.id" :value="st.id">{{ st.name }}</option>
                                </select>
                            </div>

                        </div>

                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 grid grid-cols-3 gap-2 shrink-0">
                            <button @click="downloadReport(`reports.${activeModal}.pdf`,
                                activeModal==='progress' ? progressForm : activeModal==='issues' ? issueForm : activeModal==='executive' ? executiveForm : treeForm)"
                                class="flex flex-col items-center justify-center p-3 bg-white border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition shadow-sm hover:shadow">
                                <span class="font-black text-lg">PDF</span>
                            </button>
                            <button @click="downloadReport(`reports.${activeModal}.excel`,
                                activeModal==='progress' ? progressForm : activeModal==='issues' ? issueForm : activeModal==='executive' ? executiveForm : treeForm)"
                                class="flex flex-col items-center justify-center p-3 bg-white border border-green-200 text-green-600 rounded-lg hover:bg-green-50 transition shadow-sm hover:shadow">
                                <span class="font-black text-lg">EXCEL</span>
                            </button>
                            <button @click="downloadReport(`reports.${activeModal}.csv`,
                                activeModal==='progress' ? progressForm : activeModal==='issues' ? issueForm : activeModal==='executive' ? executiveForm : treeForm)"
                                class="flex flex-col items-center justify-center p-3 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition shadow-sm hover:shadow">
                                <span class="font-black text-lg">CSV</span>
                            </button>
                        </div>

                    </div>
                </div>
            </Teleport>

        </div>
    </PeaSidebarLayout>
</template>

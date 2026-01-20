<script setup>
import { Head } from '@inertiajs/vue3';
import PeaSidebarLayout from '@/Layouts/PeaSidebarLayout.vue';

const downloadPdf = () => {
    // เปิดหน้า PDF ใน Tab ใหม่
    window.open(route('reports.export'), '_blank');
};
</script>

<template>
    <Head title="รายงาน" />
    <PeaSidebarLayout>
        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-[1600px] mx-auto space-y-6">
            <h2 class="text-3xl font-extrabold text-[#4A148C]">ระบบรายงาน (Reports Center)</h2>
            <p class="text-gray-500">ดาวน์โหลดเอกสารสรุปผลการดำเนินงาน</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center text-red-600 mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">รายงานสรุปภาพรวม (PDF)</h3>
                    <p class="text-sm text-gray-500 mt-2 mb-4">สรุปยุทธศาสตร์ แผนงาน และโครงการทั้งหมด พร้อมสถานะงบประมาณ</p>
                    <button @click="downloadPdf" class="w-full py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-bold transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        ดาวน์โหลด PDF
                    </button>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 opacity-60">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center text-green-600 mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">รายงาน Excel (Coming Soon)</h3>
                    <p class="text-sm text-gray-500 mt-2 mb-4">ไฟล์ Spreadsheet สำหรับนำไปคำนวณต่อ</p>
                    <button disabled class="w-full py-2 bg-gray-100 text-gray-400 rounded-lg font-bold cursor-not-allowed">เร็วๆ นี้</button>
                </div>
            </div>
        </div>
    </PeaSidebarLayout>
</template>

<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    items: Array
});

const flattenData = (nodes, result = []) => {
    nodes.forEach(node => {
        if ((node.type === 'project' || node.type === 'task') && node.planned_start_date && node.planned_end_date) {
            result.push({
                x: node.name,
                y: [new Date(node.planned_start_date).getTime(), new Date(node.planned_end_date).getTime()],
                fillColor: node.type === 'project' ? '#7A2F8F' : '#FDB913',
                progress: node.progress,
                status: node.status,
                budget: node.budget
            });
        }
        if (node.children) flattenData(node.children, result);
    });
    return result;
};

const series = computed(() => [{ data: flattenData(props.items) }]);

const chartOptions = computed(() => ({
    chart: {
        type: 'rangeBar',
        height: 450,
        toolbar: { show: true },
        fontFamily: 'Inherit'
    },
    plotOptions: {
        bar: {
            horizontal: true,
            barHeight: '40%',
            borderRadius: 4
        }
    },
    xaxis: {
        type: 'datetime',
        labels: {
            style: { fontSize: '12px' },
            // *** แปลงแกนเวลาเป็นภาษาไทย ***
            formatter: function(val) {
                return new Date(val).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' });
            }
        }
    },
    grid: {
        borderColor: '#f3f4f6',
        xaxis: { lines: { show: true } }
    },
    tooltip: {
        custom: function({ seriesIndex, dataPointIndex, w }) {
            const data = w.config.series[seriesIndex].data[dataPointIndex];
            const start = new Date(data.y[0]).toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' });
            const end = new Date(data.y[1]).toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' });
            return `
                <div class="px-3 py-2 text-xs bg-white border border-gray-200 shadow-lg rounded-lg">
                    <div class="font-bold text-[#7A2F8F] mb-1">${data.x}</div>
                    <div class="text-gray-600 mb-1">📅 ${start} - ${end}</div>
                    <div class="flex justify-between gap-4">
                        <span class="text-gray-500">Progress: <b class="text-gray-800">${data.progress}%</b></span>
                        <span class="text-gray-500">Budget: <b class="text-gray-800">${Number(data.budget).toLocaleString()}</b></span>
                    </div>
                </div>
            `;
        }
    }
}));
</script>

<template>
    <div class="h-full">
        <VueApexCharts type="rangeBar" height="100%" :options="chartOptions" :series="series" />
    </div>
</template>

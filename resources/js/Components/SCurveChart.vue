<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    categories: Array,
    planned: Array,
    actual: Array
});

const chartOptions = computed(() => ({
    chart: {
        id: 's-curve-chart',
        type: 'line',
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: 'Prompt, sans-serif'
    },
    colors: ['#9CA3AF', '#7A2F8F'],
    stroke: {
        curve: 'smooth',
        width: [2, 4],
        dashArray: [5, 0]
    },
    xaxis: {
        categories: props.categories,
        labels: { style: { colors: '#6B7280', fontSize: '10px' }, rotate: -45, rotateAlways: false },
        tooltip: { enabled: false }
    },
    yaxis: {
        min: 0,
        max: 100,
        tickAmount: 5,
        labels: { formatter: (value) => value.toFixed(0) + '%' }
    },
    legend: { position: 'top', horizontalAlign: 'right' },
    grid: { borderColor: '#F3F4F6', strokeDashArray: 4 },
    markers: { size: [0, 4], hover: { size: 6 } }
}));

const series = computed(() => [
    { name: 'แผนงานสะสม (Planned)', data: props.planned },
    { name: 'ผลงานสะสม (Actual)', data: props.actual }
]);
</script>

<template>
    <div class="w-full h-full min-h-[350px]">
        <VueApexCharts type="line" height="350" :options="chartOptions" :series="series" />
    </div>
</template>

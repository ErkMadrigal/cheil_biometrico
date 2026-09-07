<script setup>
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
} from 'chart.js'
import { useThemeStore } from '../stores/theme'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip)

const props = defineProps({
  // [{ day: '2026-08-03', total: 12 }, ...]
  points: { type: Array, default: () => [] },
})

const theme = useThemeStore()

const labels = computed(() =>
  props.points.map((p) =>
    new Date(p.day + 'T00:00:00').toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric' }),
  ),
)

const chartData = computed(() => ({
  labels: labels.value,
  datasets: [
    {
      label: 'Checadas',
      data: props.points.map((p) => Number(p.total)),
      borderColor: '#5b5bf6',
      backgroundColor: (ctx) => {
        const { chart } = ctx
        const { ctx: canvasCtx, chartArea } = chart
        if (!chartArea) return 'rgba(91, 91, 246, 0.15)'
        const gradient = canvasCtx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)
        gradient.addColorStop(0, 'rgba(91, 91, 246, 0.35)')
        gradient.addColorStop(1, 'rgba(91, 91, 246, 0)')
        return gradient
      },
      fill: true,
      tension: 0.4,
      borderWidth: 2.5,
      pointRadius: 0,
      pointHoverRadius: 5,
      pointHoverBackgroundColor: '#5b5bf6',
      pointHoverBorderColor: '#fff',
      pointHoverBorderWidth: 2,
    },
  ],
}))

const chartOptions = computed(() => {
  const gridColor = theme.dark ? 'rgba(148,163,184,0.1)' : 'rgba(148,163,184,0.2)'
  const textColor = theme.dark ? '#94a3b8' : '#64748b'

  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: theme.dark ? '#1e293b' : '#0f172a',
        titleColor: '#fff',
        bodyColor: '#e2e8f0',
        padding: 10,
        cornerRadius: 8,
        displayColors: false,
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: textColor, font: { size: 11 } },
      },
      y: {
        beginAtZero: true,
        grid: { color: gridColor },
        ticks: { color: textColor, font: { size: 11 }, precision: 0 },
      },
    },
  }
})
</script>

<template>
  <div class="h-64 w-full">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>

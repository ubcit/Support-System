import './bootstrap';
import ApexCharts from 'apexcharts';

// flatpickr
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
// FullCalendar
import { Calendar } from '@fullcalendar/core';

// Alpine is provided by Livewire — do NOT import/start a second Alpine instance.
window.ApexCharts = ApexCharts;
window.flatpickr = flatpickr;
window.FullCalendar = Calendar;

// Initialize optional demo components only when their DOM targets exist
const bootTailadminWidgets = () => {
    if (document.querySelector('#mapOne')) {
        import('./components/map').then(module => module.initMap());
    }

    if (document.querySelector('#chartOne')) {
        import('./components/chart/chart-1').then(module => module.initChartOne());
    }
    if (document.querySelector('#chartTwo')) {
        import('./components/chart/chart-2').then(module => module.initChartTwo());
    }
    if (document.querySelector('#chartThree')) {
        import('./components/chart/chart-3').then(module => module.initChartThree());
    }
    if (document.querySelector('#chartSix')) {
        import('./components/chart/chart-6').then(module => module.initChartSix());
    }
    if (document.querySelector('#chartEight')) {
        import('./components/chart/chart-8').then(module => module.initChartEight());
    }
    if (document.querySelector('#chartThirteen')) {
        import('./components/chart/chart-13').then(module => module.initChartThirteen());
    }

    if (document.querySelector('#calendar') && !document.querySelector('#calendar').dataset.fcReady) {
        import('./components/calendar-init').then(module => module.calendarInit());
    }

    if (document.querySelector('#task-schedule-calendar') && !document.querySelector('#task-schedule-calendar').dataset.fcReady) {
        import('./components/task-schedule').then(module => module.initTaskSchedule());
    }
};

document.addEventListener('DOMContentLoaded', bootTailadminWidgets);
document.addEventListener('livewire:navigated', bootTailadminWidgets);

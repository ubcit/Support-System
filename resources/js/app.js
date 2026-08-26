import './bootstrap';
import './tailadmin/app.js';
import './floating-panel.js';
import './task-selection.js';

document.addEventListener('livewire:navigated', () => {
    window.Alpine?.store('theme')?.updateTheme();
});

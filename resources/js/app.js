import './bootstrap';
import './tailadmin/app.js';
import './floating-panel.js';

document.addEventListener('livewire:navigated', () => {
    window.Alpine?.store('theme')?.updateTheme();
});

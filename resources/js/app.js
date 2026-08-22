import './bootstrap';
import './tailadmin/app.js';

document.addEventListener('livewire:navigated', () => {
    window.Alpine?.store('theme')?.updateTheme();
});

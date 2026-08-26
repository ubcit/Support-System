function createTaskSelStore() {
    return {
        selectedIds: [],
        hydrate(ids) {
            this.selectedIds = (Array.isArray(ids) ? ids : []).map(Number);
        },
        isSelected(id) {
            return this.selectedIds.includes(Number(id));
        },
        allSelected(ids) {
            const list = (ids || []).map(Number);

            return list.length > 0 && list.every((id) => this.selectedIds.includes(id));
        },
        someSelected(ids) {
            const list = (ids || []).map(Number);
            if (list.length === 0) {
                return false;
            }
            const selected = list.filter((id) => this.selectedIds.includes(id)).length;

            return selected > 0 && selected < list.length;
        },
        /**
         * Group / master checkbox: trust the native checked state from @change
         * (no .prevent) so the clicked box paints instantly.
         */
        setGroup(ids, checked) {
            const list = (ids || []).map(Number);
            const set = new Set(this.selectedIds);
            if (checked) {
                list.forEach((id) => set.add(id));
            } else {
                list.forEach((id) => set.delete(id));
            }
            this.selectedIds = Array.from(set);
            this.syncSilent();
        },
        clear() {
            this.selectedIds = [];
            this.syncSilent();
        },
        /**
         * Push selection into Livewire without a network round-trip / morph.
         */
        syncSilent(wire) {
            const ids = [...this.selectedIds];
            const target = wire || this.resolveWire();
            if (target && typeof target.set === 'function') {
                target.set('selectedTasks', ids, false);

                return;
            }
            if (target && typeof target.$set === 'function') {
                target.$set('selectedTasks', ids, false);
            }
        },
        resolveWire() {
            const el = document.querySelector('[data-task-dashboard]');
            if (!el || !window.Livewire) {
                return null;
            }

            return window.Livewire.find(el.getAttribute('wire:id'));
        },
        sync(wire) {
            this.syncSilent(wire);
        },
    };
}

document.addEventListener('alpine:init', () => {
    if (window.__taskSelStoreDefined) {
        return;
    }
    window.__taskSelStoreDefined = true;
    window.Alpine.store('taskSel', createTaskSelStore());
});

document.addEventListener('livewire:init', () => {
    Livewire.on('task-selection-cleared', () => {
        window.Alpine?.store('taskSel')?.hydrate([]);
    });
});

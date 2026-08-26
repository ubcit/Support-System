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
        toggle(id, wire) {
            id = Number(id);
            const set = new Set(this.selectedIds);
            if (set.has(id)) {
                set.delete(id);
            } else {
                set.add(id);
            }
            // New array reference so every :checked binding re-evaluates immediately.
            this.selectedIds = Array.from(set);
            this.sync(wire);

            return this.isSelected(id);
        },
        toggleAll(ids, wire) {
            const list = (ids || []).map(Number);
            const set = new Set(this.selectedIds);
            if (this.allSelected(list)) {
                list.forEach((id) => set.delete(id));
            } else {
                list.forEach((id) => set.add(id));
            }
            this.selectedIds = Array.from(set);
            this.sync(wire);

            return this.allSelected(list);
        },
        clear(wire) {
            this.selectedIds = [];
            this.sync(wire);
        },
        sync(wire) {
            const ids = [...this.selectedIds];
            if (wire && typeof wire.set === 'function') {
                wire.set('selectedTasks', ids);

                return;
            }
            const el = document.querySelector('[data-task-dashboard]');
            if (!el || !window.Livewire) {
                return;
            }
            const component = window.Livewire.find(el.getAttribute('wire:id'));
            if (component && typeof component.$set === 'function') {
                component.$set('selectedTasks', ids);
            }
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

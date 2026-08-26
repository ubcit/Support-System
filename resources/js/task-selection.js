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
        toggle(id, wire) {
            id = Number(id);
            const i = this.selectedIds.indexOf(id);
            if (i >= 0) {
                this.selectedIds.splice(i, 1);
            } else {
                this.selectedIds.push(id);
            }
            this.sync(wire);
        },
        toggleAll(ids, wire) {
            const list = (ids || []).map(Number);
            if (this.allSelected(list)) {
                this.selectedIds = this.selectedIds.filter((id) => !list.includes(id));
            } else {
                const set = new Set(this.selectedIds);
                list.forEach((id) => set.add(id));
                this.selectedIds = Array.from(set);
            }
            this.sync(wire);
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

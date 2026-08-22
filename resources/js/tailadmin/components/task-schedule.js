import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

export function initTaskSchedule() {
    const el = document.querySelector('#task-schedule-calendar');
    if (!el) {
        return;
    }

    const eventsUrl = el.dataset.eventsUrl;
    const rescheduleUrlTemplate = el.dataset.rescheduleUrl;

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek',
        },
        height: 'auto',
        editable: true,
        navLinks: true,
        events: async (info, success, failure) => {
            try {
                const url = new URL(eventsUrl, window.location.origin);
                url.searchParams.set('start', info.startStr.slice(0, 10));
                url.searchParams.set('end', info.endStr.slice(0, 10));
                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('Failed to load schedule events');
                }
                success(await response.json());
            } catch (error) {
                failure(error);
            }
        },
        eventClick: (info) => {
            info.jsEvent.preventDefault();
            if (info.event.url) {
                window.location.href = info.event.url;
            }
        },
        eventDrop: async (info) => {
            const uuid = info.event.extendedProps?.uuid;
            const due = info.event.startStr?.slice(0, 10);
            if (!uuid || !due || !rescheduleUrlTemplate) {
                info.revert();
                return;
            }

            try {
                const response = await fetch(rescheduleUrlTemplate.replace('__UUID__', uuid), {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        due_date: due,
                        start_date: due,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Reschedule failed');
                }

                const payload = await response.json();
                const status = document.querySelector('[data-schedule-status]');
                if (status) {
                    status.textContent = `Rescheduled “${payload.task?.title || 'task'}” to ${due}.`;
                    status.classList.remove('hidden');
                }
            } catch (error) {
                info.revert();
            }
        },
        eventClassNames: (arg) => {
            const key = arg.event.extendedProps?.calendar || 'Primary';
            return [`fc-event-${String(key).toLowerCase()}`];
        },
    });

    calendar.render();
    el.dataset.fcReady = '1';
}

// admin/js/calendar.js
(function () {
    'use strict';

    if (!window.AdminUtils) {
        // Fallback error if the shared utilities are missing
        console.error('AdminUtils missing. Calendar disabled.');
        return;
    }

    // Pull things off AdminUtils using older syntax
    var ApiClient = window.AdminUtils.ApiClient;
    var DomUtils = window.AdminUtils.DomUtils;
    var showSuccess = window.AdminUtils.showSuccess;
    var showError = window.AdminUtils.showError;

    // Safely read endpoints without optional chaining
    var endpoints =
        window.ADMIN_CONFIG && window.ADMIN_CONFIG.endpoints
            ? window.ADMIN_CONFIG.endpoints
            : {};

    var calendarEndpoint = endpoints.calendar || 'api/bookings.php';
    var bookingEndpoint = endpoints.booking || 'api/booking.php';

    var manualModal = document.getElementById('manualTaskModal');
    var manualForm = document.getElementById('manualTaskForm');
    var manualToggle = document.getElementById('openManualTask');

    // Small helper for DOM ready, works in more environments
    function onReady(fn) {
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(fn, 0);
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    onReady(function () {
        initCalendar();
        initManualTask();
    });

    function initCalendar() {
        var calendarEl = document.getElementById('calendar');
        if (!calendarEl || typeof FullCalendar === 'undefined') {
            showError('Calendar library not available.');
            return;
        }

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },

            // Make sure prev/next show arrows (and label other buttons)
            buttonText: {
                today: 'Today',
                month: 'Month',
                week: 'Week',
                day: 'Day',
                list: 'List',
                prev: '‹',
                next: '›'
            },

            selectable: true,
            eventDisplay: 'block',

            // Fetch events from the server
            events: fetchCalendarEvents,

            // Use older function syntax
            dateClick: function (info) {
                openManualModal(info.dateStr);
            },
            eventClick: function (info) {
                handleEventClick(info.event);
            }
        });

        calendar.render();
    }

    function fetchCalendarEvents(fetchInfo, successCallback, failureCallback) {
        try {
            var viewMap = {
                dayGridMonth: 'month',
                timeGridWeek: 'week',
                timeGridDay: 'day',
                listWeek: 'week'
            };

            var viewType = fetchInfo.view ? fetchInfo.view.type : null;
            var view = viewMap[viewType] || 'month';

            var dateStr;
            if (fetchInfo.startStr) {
                dateStr = fetchInfo.startStr.substring(0, 10);
            } else {
                dateStr = new Date().toISOString().substring(0, 10);
            }

            ApiClient.get(calendarEndpoint, {
                view: view,
                date: dateStr
            })
                .then(function (data) {
                    var events = [];
                    var rawEvents = (data && data.events) ? data.events : [];

                    for (var i = 0; i < rawEvents.length; i++) {
                        var event = rawEvents[i];

                        var titleBase = event.service_name || event.service_type || 'Booking';
                        var customerName = event.customer_name || '';
                        var color = event.status_color || '#14b8a6';

                        var ev = {
                            id: event.id,
                            title: titleBase + ' - ' + customerName,
                            start: event.date + 'T' + normalizeTime(event.start_time),
                            backgroundColor: color,
                            borderColor: color,
                            extendedProps: event
                        };

                        if (event.end_time) {
                            ev.end = event.date + 'T' + normalizeTime(event.end_time);
                        }

                        events.push(ev);
                    }

                    successCallback(events);
                })
                .catch(function (error) {
                    if (typeof failureCallback === 'function') {
                        failureCallback(error);
                    } else {
                        showError('Failed to load calendar events.');
                    }
                });
        } catch (error) {
            if (typeof failureCallback === 'function') {
                failureCallback(error);
            } else {
                showError('Unexpected error loading calendar events.');
            }
        }
    }

    function normalizeTime(timeString) {
        if (!timeString) {
            return '09:00:00';
        }
        if (timeString.length === 5) {
            // "HH:MM" → "HH:MM:00"
            return timeString + ':00';
        }
        return timeString;
    }

    function handleEventClick(event) {
        var bookingId = event && event.id;
        if (bookingId && typeof window.dashboardShowBookingDetail === 'function') {
            window.dashboardShowBookingDetail(bookingId);
        }
    }

    function initManualTask() {
        if (!manualModal || !manualForm) {
            return;
        }

        if (manualToggle) {
            manualToggle.addEventListener('click', function () {
                openManualModal();
            });
        }

        manualModal.addEventListener('click', function (e) {
            var event = e || window.event;
            var target = event.target || event.srcElement;

            if (
                target === manualModal ||
                (target.getAttribute && target.getAttribute('data-action') === 'close-modal')
            ) {
                closeManualModal();
            }
        });

        manualForm.addEventListener('submit', function (e) {
            var event = e || window.event;
            if (event.preventDefault) {
                event.preventDefault();
            } else {
                event.returnValue = false; // IE fallback
            }

            var formData = new FormData(manualForm);
            var payload = {};

            // Older-safe conversion from FormData to simple object
            formData.forEach(function (value, key) {
                payload[key] = value;
            });

            if (!payload.appointment_date || !payload.appointment_time) {
                showError('Date and time required.');
                return;
            }

            // Add action field
            payload.action = 'create_manual';

            ApiClient.post(bookingEndpoint, payload)
                .then(function () {
                    showSuccess('Manual task added');
                    closeManualModal();
                    window.location.reload();
                })
                .catch(function () {
                    // Errors handled globally by ApiClient / AdminUtils
                });
        });
    }

    function openManualModal(dateStr) {
        if (
            dateStr &&
            manualForm &&
            manualForm.elements &&
            manualForm.elements['appointment_date']
        ) {
            manualForm.elements['appointment_date'].value = dateStr;
        }

        manualModal.classList.remove('hidden');
        manualModal.classList.add('flex');
    }

    function closeManualModal() {
        manualModal.classList.add('hidden');
        manualModal.classList.remove('flex');

        if (manualForm && typeof manualForm.reset === 'function') {
            manualForm.reset();
        }
    }
})();

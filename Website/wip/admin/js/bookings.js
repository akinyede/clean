// admin/js/bookings.js - booking management helpers for the list page
(function (global) {
    'use strict';

    if (!global.AdminUtils) {
        console.error('AdminUtils not found. Bookings page scripts disabled.');
        return;
    }

    const { ApiClient, DomUtils, showSuccess, showError } = global.AdminUtils;
    const endpoints = window.ADMIN_CONFIG?.endpoints || {};
    const bookingApi = endpoints.booking || 'api/booking.php';
    const assignmentApi = endpoints.assignments || 'api/assignments.php';
    const staffApi = endpoints.staff || 'api/staff.php';

    const state = {
        selected: new Set(),
        staff: Array.isArray(window.BOOKINGS_PAGE?.staff) ? window.BOOKINGS_PAGE.staff : [],
    };

    document.addEventListener('DOMContentLoaded', () => {
        initSelection();
        initDropdowns();
    });

    function initSelection() {
        document.querySelectorAll('.booking-checkbox').forEach((checkbox) => {
            checkbox.addEventListener('change', (event) => {
                const bookingId = event.target.value;
                if (!bookingId) return;
                if (event.target.checked) {
                    state.selected.add(bookingId);
                } else {
                    state.selected.delete(bookingId);
                }
                updateBulkActionsUI();
            });
        });

        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.addEventListener('change', (event) => {
                const checked = event.target.checked;
                document.querySelectorAll('.booking-checkbox').forEach((checkbox) => {
                    checkbox.checked = checked;
                    if (checked) {
                        state.selected.add(checkbox.value);
                    } else {
                        state.selected.delete(checkbox.value);
                    }
                });
                updateBulkActionsUI();
            });
        }

        const bulkButton = document.getElementById('bulkActionButton');
        bulkButton?.addEventListener('click', executeBulkAction);
    }

    function initDropdowns() {
        document.addEventListener('click', (event) => {
            const toggle = event.target.closest('.dropdown-toggle');
            if (toggle) {
                const dropdown = toggle.closest('.relative').querySelector('.dropdown-menu');
                dropdown?.classList.toggle('hidden');
                return;
            }
            document.querySelectorAll('.dropdown-menu').forEach((menu) => menu.classList.add('hidden'));
        });
    }

    function updateBulkActionsUI() {
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');
        if (!bulkActions || !selectedCount) return;

        if (state.selected.size > 0) {
            bulkActions.style.display = 'block';
            selectedCount.textContent = `${state.selected.size} selected`;
        } else {
            bulkActions.style.display = 'none';
        }
    }

    function clearSelection() {
        state.selected.clear();
        document.querySelectorAll('.booking-checkbox').forEach((checkbox) => (checkbox.checked = false));
        updateBulkActionsUI();
    }

    async function executeBulkAction() {
        const action = document.getElementById('bulkAction')?.value;
        const bookingIds = Array.from(state.selected);
        if (!action || bookingIds.length === 0) {
            showError('Select at least one booking and an action.');
            return;
        }

        switch (action) {
            case 'assign_staff':
                openAssignModal(bookingIds);
                break;
            case 'confirm':
                await bulkUpdateStatus(bookingIds, 'confirmed');
                break;
            case 'cancel':
                openCancelModal(bookingIds);
                break;
            case 'reschedule':
                openRescheduleModal(bookingIds);
                break;
            case 'delete':
                await bulkDelete(bookingIds);
                break;
            default:
                showError('Unsupported bulk action.');
        }
    }

    async function bulkUpdateStatus(ids, statusLabel) {
        if (!confirm(`Update ${ids.length} booking(s) to ${statusLabel}?`)) {
            return;
        }
        try {
            await ApiClient.post(bookingApi, {
                action: 'bulk_update_status',
                booking_ids: ids,
                status_label: statusLabel,
            });
            showSuccess('Statuses updated');
            clearSelection();
            location.reload();
        } catch (error) {
            // handled globally
        }
    }

    async function bulkDelete(ids) {
        if (!confirm('Delete selected bookings? This cannot be undone.')) {
            return;
        }
        try {
            await ApiClient.request(bookingApi, {
                method: 'DELETE',
                body: JSON.stringify({ booking_ids: ids }),
            });
            showSuccess('Bookings deleted');
            clearSelection();
            location.reload();
        } catch (error) {
            // handled
        }
    }

    function showEditModal(bookingId) {
        loadBooking(bookingId).then((booking) => {
            const modal = createModal('editBookingModal');
            modal.innerHTML = `
                <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Booking</p>
                            <h2 class="text-xl font-semibold text-slate-900">${DomUtils.escapeHtml(booking.booking_id)}</h2>
                        </div>
                        <button class="text-slate-500 hover:text-slate-700" data-action="close-modal">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <form id="editBookingForm" class="px-6 py-4 space-y-4">
                        ${renderInput('first_name', 'First Name', booking.first_name)}
                        ${renderInput('last_name', 'Last Name', booking.last_name)}
                        ${renderInput('email', 'Email', booking.email, 'email')}
                        ${renderInput('phone', 'Phone', booking.raw_phone)}
                        ${renderInput('address', 'Address', booking.address)}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            ${renderInput('city', 'City', booking.city)}
                            ${renderInput('state', 'State', booking.state)}
                            ${renderInput('zip', 'ZIP', booking.zip)}
                        </div>
                        ${renderTextarea('notes', 'Notes', booking.notes)}
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" data-action="close-modal" class="btn-secondary">Cancel</button>
                            <button type="submit" class="btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);
            document.body.classList.add('overflow-hidden');

            modal.addEventListener('click', (event) => {
                if (event.target.matches('[data-action="close-modal"]') || event.target === modal) {
                    modal.remove();
                    document.body.classList.remove('overflow-hidden');
                }
            });

            document.getElementById('editBookingForm')?.addEventListener('submit', async (event) => {
                event.preventDefault();
                const formData = new FormData(event.target);
                const updates = Object.fromEntries(formData.entries());
                try {
                    await ApiClient.put(bookingApi, {
                        booking_id: booking.booking_id,
                        updates,
                    });
                    showSuccess('Booking updated');
                    modal.remove();
                    document.body.classList.remove('overflow-hidden');
                    location.reload();
                } catch (error) {
                    // handled
                }
            });
        });
    }

    function openRescheduleModal(bookingIds) {
        const ids = Array.isArray(bookingIds) ? bookingIds : [bookingIds];
        const modal = createModal('rescheduleModal');
        modal.innerHTML = `
            <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Reschedule ${ids.length > 1 ? 'Bookings' : 'Booking'}</h2>
                        <p class="text-sm text-slate-500">Select a new date and time.</p>
                    </div>
                    <button data-action="close-modal" class="text-slate-500 hover:text-slate-700">&times;</button>
                </div>
                <form id="rescheduleForm" class="px-6 py-4 space-y-4">
                    ${renderInput('appointment_date', 'New Date', '', 'date')}
                    ${renderInput('appointment_time', 'New Time', '', 'time')}
                    ${renderTextarea('reason', 'Reason (optional)', '')}
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" data-action="close-modal" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary">Apply</button>
                    </div>
                </form>
            </div>
        `;
        document.body.appendChild(modal);
        document.body.classList.add('overflow-hidden');
        attachModalClose(modal);

        document.getElementById('rescheduleForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(event.target);
            const date = formData.get('appointment_date');
            const time = formData.get('appointment_time');
            const reason = formData.get('reason') || 'Rescheduled';
            if (!date || !time) {
                showError('Date and time required.');
                return;
            }
            try {
                const payload = {
                    action: ids.length > 1 ? 'bulk_reschedule' : 'reschedule',
                    booking_ids: ids.length > 1 ? ids : undefined,
                    booking_id: ids.length === 1 ? ids[0] : undefined,
                    appointment_date: date,
                    appointment_time: time,
                    reason,
                };
                await ApiClient.post(bookingApi, payload);
                showSuccess('Reschedule saved');
                modal.remove();
                document.body.classList.remove('overflow-hidden');
                clearSelection();
                location.reload();
            } catch (error) {
                // handled
            }
        });
    }

    function openCancelModal(bookingIds) {
        const ids = Array.isArray(bookingIds) ? bookingIds : [bookingIds];
        const modal = createModal('cancelModal');
        modal.innerHTML = `
            <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Cancel ${ids.length > 1 ? 'Bookings' : 'Booking'}</h2>
                        <p class="text-sm text-slate-500">Clients and assigned staff will be notified.</p>
                    </div>
                    <button data-action="close-modal" class="text-slate-500 hover:text-slate-700">&times;</button>
                </div>
                <form id="cancelBookingForm" class="px-6 py-4 space-y-4">
                    ${renderTextarea('reason', 'Reason', 'Cancelled via admin dashboard')}
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" data-action="close-modal" class="btn-secondary">Keep Booking</button>
                        <button type="submit" class="btn-primary bg-rose-500 hover:bg-rose-600">Cancel Booking(s)</button>
                    </div>
                </form>
            </div>
        `;
        document.body.appendChild(modal);
        document.body.classList.add('overflow-hidden');
        attachModalClose(modal);

        document.getElementById('cancelBookingForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const reason = new FormData(event.target).get('reason') || 'Cancelled via admin dashboard';
            try {
                const payload = {
                    action: ids.length > 1 ? 'bulk_cancel' : 'cancel',
                    booking_ids: ids.length > 1 ? ids : undefined,
                    booking_id: ids.length === 1 ? ids[0] : undefined,
                    reason,
                };
                await ApiClient.post(bookingApi, payload);
                showSuccess('Booking cancelled');
                modal.remove();
                document.body.classList.remove('overflow-hidden');
                clearSelection();
                location.reload();
            } catch (error) {
                // handled
            }
        });
    }

    function openAssignModal(bookingIds) {
        const ids = Array.isArray(bookingIds) ? bookingIds : [bookingIds];
        loadStaff().then(() => {
            const modal = createModal('assignStaffModal');
            modal.innerHTML = `
                <div class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Assign Staff</h2>
                            <p class="text-sm text-slate-500">${ids.length > 1 ? `${ids.length} bookings selected` : `Booking ${ids[0]}`}</p>
                        </div>
                        <button data-action="close-modal" class="text-slate-500 hover:text-slate-700">&times;</button>
                    </div>
                    <form id="assignStaffForm" class="px-6 py-4 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            ${state.staff.map((staff) => `
                                <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 hover:border-rose-300">
                                    <input type="checkbox" name="staff_ids" value="${staff.id}" class="rounded border-slate-300 text-rose-500 focus:ring-rose-300">
                                    <div>
                                        <p class="font-semibold text-slate-900">${DomUtils.escapeHtml(staff.name || `${staff.first_name} ${staff.last_name}`)}</p>
                                        <p class="text-sm text-slate-500">${DomUtils.escapeHtml(staff.role || '')}</p>
                                    </div>
                                </label>
                            `).join('')}
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" data-action="close-modal" class="btn-secondary">Cancel</button>
                            <button type="submit" class="btn-primary">Assign</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);
            document.body.classList.add('overflow-hidden');
            attachModalClose(modal);

            document.getElementById('assignStaffForm')?.addEventListener('submit', async (event) => {
                event.preventDefault();
                const formData = new FormData(event.target);
                const selectedStaff = formData.getAll('staff_ids').map((value) => parseInt(value, 10)).filter((value) => Number.isFinite(value));
                if (selectedStaff.length === 0) {
                    showError('Select at least one staff member.');
                    return;
                }
                try {
                    if (ids.length === 1) {
                        await ApiClient.post(assignmentApi, {
                            booking_id: ids[0],
                            staff_ids: selectedStaff,
                            assignment_role: 'lead',
                            send_notification: true,
                        });
                    } else {
                        for (const bookingId of ids) {
                            await ApiClient.post(assignmentApi, {
                                booking_id: bookingId,
                                staff_ids: selectedStaff,
                                assignment_role: 'assistant',
                                send_notification: true,
                            });
                        }
                    }
                    showSuccess('Staff assigned');
                    modal.remove();
                    document.body.classList.remove('overflow-hidden');
                    clearSelection();
                    location.reload();
                } catch (error) {
                    // handled
                }
            });
        });
    }

    async function updateBookingStatus(bookingId, statusLabel) {
        if (!confirm(`Change booking ${bookingId} to ${statusLabel}?`)) {
            return;
        }
        try {
            await ApiClient.put(bookingApi, {
                booking_id: bookingId,
                status_label: statusLabel,
            });
            showSuccess('Status updated');
            location.reload();
        } catch (error) {
            // handled
        }
    }

    async function sendReminder(bookingId) {
        if (!confirm('Send a reminder now?')) {
            return;
        }
        try {
            await ApiClient.post(bookingApi, {
                action: 'send_reminder',
                booking_id: bookingId,
            });
            showSuccess('Reminder sent');
        } catch (error) {
            // handled
        }
    }

    function showBookingDetail(bookingId) {
        if (typeof window.dashboardShowBookingDetail === 'function') {
            window.dashboardShowBookingDetail(bookingId);
            return;
        }
        loadBooking(bookingId).then((booking) => {
            const modal = createModal('detailModal');
            modal.innerHTML = `
                <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Booking</p>
                            <h2 class="text-xl font-semibold text-slate-900">${DomUtils.escapeHtml(booking.booking_id)}</h2>
                        </div>
                        <button data-action="close-modal" class="text-slate-500 hover:text-slate-700">&times;</button>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        <div>
                            <p class="text-xs text-slate-500 uppercase">Client</p>
                            <p class="text-lg font-semibold text-slate-900">${DomUtils.escapeHtml(booking.customer_full_name || '')}</p>
                            <p class="text-sm text-slate-500">${DomUtils.escapeHtml(booking.email || '')}</p>
                            <p class="text-sm text-slate-500">${DomUtils.escapeHtml(booking.phone || '')}</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="border rounded-xl p-4 border-slate-200">
                                <p class="text-xs text-slate-500 uppercase">Service</p>
                                <p class="font-semibold text-slate-900">${DomUtils.escapeHtml(booking.service?.name || '')}</p>
                                <p class="text-sm text-slate-500">${DomUtils.escapeHtml(booking.service?.frequency || '')}</p>
                            </div>
                            <div class="border rounded-xl p-4 border-slate-200">
                                <p class="text-xs text-slate-500 uppercase">Appointment</p>
                                <p class="font-semibold text-slate-900">${DomUtils.escapeHtml(booking.appointment?.formatted || '')}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            document.body.classList.add('overflow-hidden');
            attachModalClose(modal);
        });
    }

    async function loadBooking(bookingId) {
        const data = await ApiClient.get(`${bookingApi}?id=${encodeURIComponent(bookingId)}`);
        return data.booking || data;
    }

    async function loadStaff() {
        if (state.staff.length > 0) {
            return;
        }
        try {
            const response = await ApiClient.get(staffApi);
            state.staff = response.staff || [];
        } catch (error) {
            showError('Unable to load staff.');
        }
    }

    function createModal(id) {
        document.getElementById(id)?.remove();
        const modal = document.createElement('div');
        modal.id = id;
        modal.className = 'fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50';
        return modal;
    }

    function attachModalClose(modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal || event.target.matches('[data-action="close-modal"]')) {
                modal.remove();
                document.body.classList.remove('overflow-hidden');
            }
        });
    }

    function renderInput(name, label, value = '', type = 'text') {
        return `
            <label class="block">
                <span class="text-sm font-medium text-slate-700">${DomUtils.escapeHtml(label)}</span>
                <input type="${type}" name="${name}" value="${DomUtils.escapeHtml(value || '')}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-rose-400 focus:ring-rose-300">
            </label>
        `;
    }

    function renderTextarea(name, label, value = '') {
        return `
            <label class="block">
                <span class="text-sm font-medium text-slate-700">${DomUtils.escapeHtml(label)}</span>
                <textarea name="${name}" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-rose-400 focus:ring-rose-300">${DomUtils.escapeHtml(value || '')}</textarea>
            </label>
        `;
    }

    global.BookingsManager = {
        showEditModal,
        showRescheduleModal: openRescheduleModal,
        showCancelModal: openCancelModal,
        updateBookingStatus,
        assignStaffWithNotification: openAssignModal,
        showAssignStaffModal: openAssignModal,
        showBookingDetail,
        sendReminder,
        clearSelection,
        executeBulkAction,
    };
})(window);

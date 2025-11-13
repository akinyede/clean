// /admin/js/booking-manager.js

(function() {
    'use strict';

    // Simplified state for this page
    const BookingState = {
        currentBooking: null,
        setBooking(booking) {
            this.currentBooking = booking;
        },
        toggleGlobalLoading(show) {
            const loader = document.getElementById('globalLoader');
            if (loader) {
                loader.style.display = show ? 'flex' : 'none';
            }
        }
    };

    // API Client (Copied from Dashboard.js)
    const ApiClient = {
        async request(url, options = {}) {
            BookingState.toggleGlobalLoading(true);
            try {
                const response = await fetch(url, {
                    headers: {
                        'Content-Type': 'application/json',
                        ...options.headers
                    },
                    ...options
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message || 'Request failed');
                }
                return data;
            } catch (error) {
                console.error(`API request failed: ${url}`, error);
                this.showError(error.message);
                throw error;
            } finally {
                BookingState.toggleGlobalLoading(false);
            }
        },
        async get(url, params = {}) {
            const queryString = new URLSearchParams(params).toString();
            const fullUrl = queryString ? `${url}?${queryString}` : url;
            return this.request(fullUrl);
        },
        async post(url, data = {}) {
            return this.request(url, {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },
        async delete(url) {
            return this.request(url, { method: 'DELETE' });
        },
        showError(message) {
            this.showNotification(message, 'error');
        },
        showSuccess(message) {
            this.showNotification(message, 'success');
        },
        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg z-[10001] ${
                type === 'error' ? 'bg-red-500 text-white' :
                type === 'success' ? 'bg-green-500 text-white' :
                'bg-blue-500 text-white'
            }`;
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
    };

    // DOM Utilities (Copied from Dashboard.js)
    const DomUtils = {
        escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    };

    // --- Modal Functions (Copied from Dashboard.js) ---

    // Show Booking Detail Modal
    async function showBookingDetail(bookingId) {
        try {
            // Use the correct API path
            const data = await ApiClient.get(`/admin/api/booking-detail.php`, { id: bookingId });
            BookingState.setBooking(data.booking);
            renderBookingDetailModal(data.booking, data.assigned_staff || []);
        } catch (error) {
            // Error already handled by ApiClient
        }
    }

    function renderBookingDetailModal(booking, assignedStaff) {
        const modal = createModal('bookingDetailModal', 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4');
        modal.innerHTML = `
        <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-gradient-to-r from-rose-500 to-purple-600 text-white p-6 rounded-t-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-2xl font-bold">Booking Details</h2>
                        <p class="text-sm opacity-90 mt-1">${DomUtils.escapeHtml(booking.id)}</p>
                    </div>
                    <button onclick="window.dashboardCloseBookingDetail()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-full p-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-3">Customer Information</h3>
                    <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl">
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide">Name</p>
                            <p class="font-semibold text-slate-900">${DomUtils.escapeHtml(booking.customer_name)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide">Email</p>
                            <p class="text-slate-900">${DomUtils.escapeHtml(booking.email)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide">Phone</p>
                            <p class="text-slate-900">${DomUtils.escapeHtml(booking.phone)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide">Location</p>
                            <p class="text-slate-900 text-sm">${DomUtils.escapeHtml(booking.location.full)}</p>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-3">Service Details</h3>
                    <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl">
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide">Service</p>
                            <p class="font-semibold text-slate-900">${DomUtils.escapeHtml(booking.service.name)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide">Frequency</p>
                            <p class="text-slate-900">${DomUtils.escapeHtml(booking.service.frequency)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide">Appointment</p>
                            <p class="text-slate-900 text-sm">${DomUtils.escapeHtml(booking.appointment.formatted)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide">Price</p>
                            <p class="text-green-600 font-bold text-lg">${DomUtils.escapeHtml(booking.pricing.estimated)}</p>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-semibold text-slate-900">Assigned Staff</h3>
                        <button onclick="window.dashboardShowAssignStaffModal()" class="text-sm bg-rose-500 text-white px-4 py-2 rounded-lg hover:bg-rose-600">
                            + Assign Staff
                        </button>
                    </div>
                    <div id="assignedStaffList" class="space-y-2">
                        ${assignedStaff.length === 0 ? `
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-center">
                            <p class="text-amber-700"> No staff assigned yet</p>
                            <p class="text-xs text-amber-600 mt-1">Click "Assign Staff" to add team members</p>
                        </div>
                        ` : assignedStaff.map(staff => `
                        <div class="flex items-center justify-between bg-slate-50 p-3 rounded-lg border border-slate-200">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold"
                                    style="background: ${DomUtils.escapeHtml(staff.color_tag)}">
                                    ${DomUtils.escapeHtml(staff.name.split(' ').map(n => n[0]).join(''))}
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-900">${DomUtils.escapeHtml(staff.name)}</p>
                                    <p class="text-xs text-slate-500">${DomUtils.escapeHtml(staff.role)} | ${DomUtils.escapeHtml(staff.assignment_role)}</p>
                                </div>
                            </div>
                            <button onclick="window.dashboardUnassignStaff(${staff.id})" class="text-red-500 hover:text-red-700 text-sm">
                                Remove
                            </button>
                        </div>
                        `).join('')}
                    </div>
                </div>
                ${booking.notes ? `
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Customer Notes</h3>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-slate-700">${DomUtils.escapeHtml(booking.notes)}</p>
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
        `;
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
    }

    // Close Booking Detail Modal
    function closeBookingDetail() {
        const modal = document.getElementById('bookingDetailModal');
        if (modal) {
            modal.remove();
            document.body.style.overflow = '';
            BookingState.setBooking(null);
        }
    }

    // Show Assign Staff Modal
    async function showAssignStaffModal() {
        if (!BookingState.currentBooking) return;
        try {
            const data = await ApiClient.get(
                '/admin/api/assignments.php',
                {
                    date: BookingState.currentBooking.appointment.date,
                    time: BookingState.currentBooking.appointment.time,
                    booking_id: BookingState.currentBooking.id
                }
            );
            renderAssignStaffModal(data.staff, data.assigned || []);
        } catch (error) {
            // Error already handled by ApiClient
        }
    }

    function renderAssignStaffModal(staff, assigned) {
        const modal = createModal('assignStaffModal', 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60] p-4');
        modal.innerHTML = `
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
            <div class="sticky top-0 bg-gradient-to-r from-purple-500 to-indigo-600 text-white p-6 rounded-t-2xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold">Assign Staff to Booking</h2>
                    <button onclick="window.dashboardCloseAssignStaffModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-full p-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <p class="text-sm text-slate-600 mb-4">Select one or more staff members to assign to this booking.</p>
                <div class="space-y-3" id="staffSelectionList">
                    ${staff.map(staffMember => {
                        const isAssigned = assigned.some(a => a.id === staffMember.id);
                        return `
                        <label class="flex items-center justify-between p-4 border-2 rounded-lg cursor-pointer hover:bg-slate-50 transition ${isAssigned ? 'border-green-500 bg-green-50' : 'border-slate-200'}">
                            <div class="flex items-center gap-3">
                                <input type="checkbox"
                                    name="staff_ids"
                                    value="${DomUtils.escapeHtml(staffMember.id)}"
                                    ${isAssigned ? 'checked disabled' : ''}
                                    class="w-5 h-5 text-rose-500 rounded focus:ring-rose-400">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold"
                                    style="background: ${DomUtils.escapeHtml(staffMember.color_tag)}">
                                    ${DomUtils.escapeHtml(staffMember.name.split(' ').map(n => n[0]).join(''))}
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-900">${DomUtils.escapeHtml(staffMember.name)}</p>
                                    <p class="text-xs text-slate-500">${DomUtils.escapeHtml(staffMember.role)} | ${staffMember.bookings_count} bookings today</p>
                                </div>
                            </div>
                            <span class="text-xs px-2 py-1 rounded ${
                                staffMember.availability_status === 'available' ? 'bg-green-100 text-green-700' : 
                                staffMember.availability_status === 'busy' ? 'bg-yellow-100 text-yellow-700' : 
                                'bg-red-100 text-red-700'
                            }">
                                ${DomUtils.escapeHtml(staffMember.availability_status)}
                            </span>
                        </label>
                        `;
                    }).join('')}
                </div>
                <div class="mt-6 flex gap-3">
                    <button onclick="window.dashboardCloseAssignStaffModal()" class="flex-1 px-6 py-3 border border-slate-300 rounded-lg hover:bg-slate-50">
                        Cancel
                    </button>
                    <button onclick="window.dashboardSubmitStaffAssignment()" class="flex-1 px-6 py-3 bg-rose-500 text-white rounded-lg hover:bg-rose-600 font-semibold">
                        Assign Selected
                    </button>
                </div>
            </div>
        </div>
        `;
        document.body.appendChild(modal);
    }

    // Close Assign Staff Modal
    function closeAssignStaffModal() {
        const modal = document.getElementById('assignStaffModal');
        if (modal) modal.remove();
    }

    // Submit Staff Assignment
    async function submitStaffAssignment() {
        const checkboxes = document.querySelectorAll('input[name="staff_ids"]:checked:not(:disabled)');
        const staffIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
        if (staffIds.length === 0) {
            ApiClient.showNotification('Please select at least one staff member', 'error');
            return;
        }
        try {
            await ApiClient.post('/admin/api/assignments.php', {
                booking_id: BookingState.currentBooking.id,
                staff_ids: staffIds,
                assignment_role: 'assistant' // You could add a dropdown for this
            });
            ApiClient.showSuccess('Staff assigned successfully! They will receive notifications.');
            closeAssignStaffModal();
            closeBookingDetail();
            // We reload the page to see the change in the list.
            window.location.reload(); 
        } catch (error) {
            // Error already handled by ApiClient
        }
    }

    // Unassign Staff
    async function unassignStaff(staffId) {
        if (!confirm('Are you sure you want to remove this staff member from the booking?')) {
            return;
        }
        try {
            await ApiClient.delete(
                `/admin/api/assignments.php?booking_id=${BookingState.currentBooking.id}&staff_id=${staffId}`
            );
            ApiClient.showSuccess('Staff unassigned successfully');
            // Refresh the detail modal to show the change
            const currentBookingId = BookingState.currentBooking.id;
            closeBookingDetail();
            showBookingDetail(currentBookingId);
        } catch (error) {
            // Error already handled by ApiClient
        }
    }

    // Utility functions
    function createModal(id, className) {
        const existing = document.getElementById(id);
        if (existing) existing.remove();
        const modal = document.createElement('div');
        modal.id = id;
        modal.className = className;
        return modal;
    }

    // Expose functions to global scope for HTML onclick handlers
    window.dashboardShowBookingDetail = showBookingDetail;
    window.dashboardCloseBookingDetail = closeBookingDetail;
    window.dashboardShowAssignStaffModal = showAssignStaffModal;
    window.dashboardCloseAssignStaffModal = closeAssignStaffModal;
    window.dashboardSubmitStaffAssignment = submitStaffAssignment;
    window.dashboardUnassignStaff = unassignStaff;

})();
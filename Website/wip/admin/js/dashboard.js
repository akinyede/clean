// Enhanced Dashboard JavaScript - With fallbacks for external dependencies
(function() {
    'use strict';

    // Use shared utilities from api-client.js
    const ApiClient = window.AdminUtils.ApiClient;
    const DomUtils = window.AdminUtils.DomUtils;
    const { showSuccess, showError, showNotification } = window.AdminUtils;

    const endpoints = window.ADMIN_CONFIG?.endpoints || {};
    const basePath = window.ADMIN_CONFIG?.basePath || '';

    // State management
    let statusChartInstance = null;

    const DashboardState = {
        currentBooking: null,
        currentCalendarView: 'day',
        currentCalendarDate: new Date().toISOString().split('T')[0],
        isLoading: false,
        eventListeners: [],
        setBooking(booking) {
            this.currentBooking = booking;
        },
        setCalendarView(view) {
            this.currentCalendarView = view;
        },
        setCalendarDate(date) {
            this.currentCalendarDate = date;
        },
        setLoading(loading) {
            this.isLoading = loading;
            this.toggleGlobalLoading(loading);
        },
        toggleGlobalLoading(show) {
            ApiClient.globalLoadingToggle(show);
        }
    };

    const NotificationCenter = {
        notifications: [],
        unreadCount: 0,
        listEl: null,
        badgeEl: null,
        modalEl: null,
        toggleEl: null,
        closeEl: null,
        markAllEl: null,
        refreshEl: null,
        pollTimer: null,
    };

    // Dependency check
    const Dependencies = {
        chartJs: typeof Chart !== 'undefined',
        litepicker: typeof Litepicker !== 'undefined'
    };

    // Initialize dashboard
    document.addEventListener('DOMContentLoaded', () => {
        initializeDashboard();
    });

    function initializeDashboard() {
        loadOverviewStats();
        loadStatusChart();
        loadUpcomingJobs();
        loadCalendar(DashboardState.currentCalendarView, DashboardState.currentCalendarDate);
        loadQuickStats();
        setupEventListeners();
        setupDatePicker();
        initNotifications();

        // Refresh data every 5 minutes
        setInterval(() => {
            loadOverviewStats();
            loadStatusChart();
            loadUpcomingJobs();
            loadQuickStats();
        }, 300000);
    }

    // Event Listeners
    function setupEventListeners() {
        // Calendar view toggles
        addListener('calendarViewDay', 'click', () => loadCalendar('day'));
        addListener('calendarViewWeek', 'click', () => loadCalendar('week'));
        addListener('calendarViewMonth', 'click', () => loadCalendar('month'));
        


        // Event Delegation for Modals and Actions
        document.addEventListener('click', (e) => {
            const target = e.target.closest('[data-action]');
            if (!target) return;

            const action = target.dataset.action;
            const bookingId = target.dataset.bookingId || (DashboardState.currentBooking?.id);
            const staffId = target.dataset.staffId ? parseInt(target.dataset.staffId) : null;
            
            switch (action) {
                case 'close-detail-modal':
                    closeBookingDetail();
                    break;
                case 'show-assign-staff':
                    showAssignStaffModal();
                    break;
                case 'unassign-staff':
                    if (staffId && bookingId) unassignStaff(staffId);
                    break;
                case 'submit-assignment':
                    submitStaffAssignment();
                    break;
                case 'edit-booking':
                    showEditBookingModal(bookingId);
                    break;
                case 'show-booking-detail':
                    if (bookingId) showBookingDetail (bookingId);
                    break;
                case 'cancel-booking':
                    cancelBooking(bookingId);
                    break;
                case 'close-assign-staff-modal':
                    closeAssignStaffModal();
                    break;
            }
        });

        // Quick action buttons
        addListener('quickAssignStaff', 'click', showStaffAssignmentModal);

        // Event delegation for booking items
        document.addEventListener('click', (e) => {
            const bookingItem = e.target.closest('[data-booking-id]');
            if (bookingItem) {
                const bookingId = bookingItem.dataset.bookingId;
                showBookingDetail(bookingId);
            }
        });
    }

    function addListener(elementId, event, handler) {
        const element = document.getElementById(elementId);
        if (element) {
            element.addEventListener(event, handler);
            DashboardState.eventListeners.push({ element, event, handler });
        }
    }

    // Cleanup function
    function cleanup() {
        DashboardState.eventListeners.forEach(({ element, event, handler }) => {
            element.removeEventListener(event, handler);
        });
        DashboardState.eventListeners = [];
        if (NotificationCenter.pollTimer) {
            clearInterval(NotificationCenter.pollTimer);
            NotificationCenter.pollTimer = null;
        }
    }

    // Load Overview Stats
    async function loadOverviewStats() {
        DashboardState.setLoading(true);
        try {
            const data = await ApiClient.get(endpoints.overview);
            updateElementText('#statScheduled .stat-value', data.today?.scheduled || 0);
            updateElementText('#statInProgress .stat-value', data.today?.in_progress || 0);
            updateElementText('#statCompleted .stat-value', data.today?.completed || 0);
            updateElementText('#statCancelled .stat-value', data.today?.cancelled || 0);
        } catch (error) {
            // Error already handled by ApiClient
        } finally {
            DashboardState.setLoading(false);
        }
    }

    // Load Status Chart - With Chart.js fallback
    async function loadStatusChart() {
        try {
            const data = await ApiClient.get(endpoints.reports);
            const chartElement = document.getElementById('statusChart');
            if (!chartElement) {
                return;
            }

            const counts = data.status_counts || {};
            const chartData = [
                counts.scheduled ?? data.scheduled_count ?? 0,
                counts.in_progress ?? data.in_progress_count ?? 0,
                counts.completed ?? data.completed_count ?? 0,
                counts.cancelled ?? data.cancelled_count ?? 0
            ];

            // Use Chart.js if available, otherwise use HTML fallback
            if (Dependencies.chartJs && statusChartInstance) {
                statusChartInstance.data.datasets[0].data = chartData;
                statusChartInstance.update();
                return;
            } else if (Dependencies.chartJs) {
                // Create new Chart.js instance
                statusChartInstance = new Chart(chartElement.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Scheduled', 'In Progress', 'Completed', 'Cancelled'],
                        datasets: [{
                            label: 'Tasks by Status',
                            data: chartData,
                            backgroundColor: [
                                '#22d3ee',
                                '#f59e0b',
                                '#22c55e',
                                '#ef4444'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((sum, entry) => sum + entry, 0);
                                        const percentage = total ? ((value / total) * 100).toFixed(1) : '0.0';
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            } else {
                // HTML fallback
                const total = chartData.reduce((sum, value) => sum + value, 0);
                const colors = ['#22d3ee', '#f59e0b', '#22c55e', '#ef4444'];
                const labels = ['Scheduled', 'In Progress', 'Completed', 'Cancelled'];
                
                let chartHTML = '<div class="flex flex-col gap-2">';
                
                chartData.forEach((count, index) => {
                    const percentage = total > 0 ? ((count / total) * 100).toFixed(1) : '0.0';
                    chartHTML += `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" style="background-color: ${colors[index]}"></div>
                                <span class="text-sm text-slate-700">${labels[index]}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold">${count}</span>
                                <span class="text-xs text-slate-500">(${percentage}%)</span>
                            </div>
                        </div>
                    `;
                });
                
                chartHTML += '</div>';
                chartElement.innerHTML = chartHTML;
            }
        } catch (error) {
            // Error already handled by ApiClient
        }
    }

    // Load Upcoming Jobs
    async function loadUpcomingJobs() {
        try {
            const data = await ApiClient.get(endpoints.calendar, {
                view: 'day',
                date: new Date().toISOString().split('T')[0]
            });
            const container = document.getElementById('upcomingJobsList');
            if (!container) {
                return;
            }

            if (!data.events || data.events.length === 0) {
                container.innerHTML = '<p class="text-slate-500 text-sm">No upcoming jobs found.</p>';
                return;
            }

            const fragment = document.createDocumentFragment();
            data.events.slice(0, 5).forEach(event => {
                const details =DomUtils.createElement('div', {
                    className: 'flex items-start justify-between p-3 border border-slate-200 rounded-lg',
                    'data-booking-id': event.id
                }, [
                
                    DomUtils.createElement('div', { className: 'flex-1' }, [ DomUtils.createElement('p', { className: 'font-semibold text-slate-900' }, [
                            DomUtils.escapeHtml(event.customer_name)
                        ]),
                        DomUtils.createElement('p', { className: 'text-xs text-slate-500' }, [
                            DomUtils.escapeHtml(`${event.date} at ${event.start_time}`)
                        ]),
                        DomUtils.createElement('p', { className: 'text-xs text-slate-500' }, [
                            DomUtils.escapeHtml(event.address || 'Address to be confirmed')
                        ])
                    ]),
                    DomUtils.createElement('button', {
                        className: 'text-rose-500 hover:text-rose-600 text-sm font-semibold',
                        'data-action': 'show-booking-detail', // Use data-action
                        'data-booking-id': event.id
                    }, ['View Details'])
                ]);
                fragment.appendChild(details);
            });
            
            container.innerHTML = '';
            container.appendChild(fragment);
        } catch (error) {
            const container = document.getElementById('upcomingJobsList');
            if (container) {
                container.innerHTML = '<p class="text-rose-500 text-sm">Unable to load upcoming jobs right now.</p>';
            }
        }
    }

    // Load Quick Stats
    async function loadQuickStats() {
        try {
            const data = await ApiClient.get(endpoints.reports);
            updateElementText('#metricClients', data.total_clients || 0);
            updateElementText('#metricStaff', data.active_staff || 0);
            updateElementText('#metricRevenue', data.monthly_revenue || '$0.00');
            updateElementText('#metricBookings', data.weekly_bookings || 0);
        } catch (error) {
            // Error already handled by ApiClient
        }
    }

    // Load Calendar
    async function loadCalendar(view = 'day', date = null) {
        const targetDate = date || DashboardState.currentCalendarDate;
        DashboardState.setCalendarView(view);
        DashboardState.setCalendarDate(targetDate);
        try {
            const data = await ApiClient.get(endpoints.calendar, { view, date: targetDate });
            renderCalendarEvents(data.events, view);
            updateActiveViewButton(view);
        } catch (error) {
            // Error already handled by ApiClient
        }
    }

    // Render Calendar Events
    function renderCalendarEvents(events, view) {
        const container = document.getElementById('calendarContainer');
        if (!events || events.length === 0) {
            const emptyMarkup = [
                '<div class="calendar-empty">',
                '    <p class="text-slate-500">No bookings found for this period.</p>',
                '    <p class="text-xs text-slate-400">Select a different date range or create a new booking.</p>',
                '</div>'
            ].join('');
            container.innerHTML = emptyMarkup;
            return;
        }

        const fragment = document.createDocumentFragment();

        events.forEach(event => {
            const hasStaff = Array.isArray(event.staff) && event.staff.length > 0;

            const card = document.createElement('div');
            card.className = 'bg-white border border-slate-200 rounded-xl p-4 hover:shadow-md transition cursor-pointer';
            card.style.borderLeft = '4px solid ' + (event.status_color || '#a855f7');
            card.dataset.bookingId = event.id;

            const wrapper = document.createElement('div');
            wrapper.className = 'flex items-start justify-between gap-3';
            card.appendChild(wrapper);

            const leftColumn = document.createElement('div');
            leftColumn.className = 'flex-1 space-y-1';
            wrapper.appendChild(leftColumn);

            const headerRow = document.createElement('div');
            headerRow.className = 'flex items-center gap-2 flex-wrap mb-1';
            leftColumn.appendChild(headerRow);

            const nameSpan = document.createElement('span');
            nameSpan.className = 'font-semibold text-slate-900';
            nameSpan.textContent = event.customer_name;
            headerRow.appendChild(nameSpan);

            const staffSpan = document.createElement('span');
            staffSpan.className = hasStaff
                ? 'text-xs bg-green-100 text-green-700 px-2 py-1 rounded'
                : 'text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded';
            staffSpan.textContent = hasStaff ? 'Staff assigned' : 'Awaiting staff';
            headerRow.appendChild(staffSpan);

            const statusSpan = document.createElement('span');
            statusSpan.className = 'text-xs px-2 py-1 rounded';
            statusSpan.style.background = (event.status_color || '#a855f7') + '20';
            statusSpan.style.color = event.status_text_color || '#6b7280';
            statusSpan.textContent = event.status_label || 'Scheduled';
            headerRow.appendChild(statusSpan);

            const serviceLine = document.createElement('p');
            serviceLine.className = 'text-sm text-slate-600';
            const serviceStrong = document.createElement('strong');
            serviceStrong.textContent = event.service_name || 'Service';
            serviceLine.appendChild(serviceStrong);
            serviceLine.appendChild(document.createTextNode(' (' + (event.duration_minutes || 0) + ' min)'));
            leftColumn.appendChild(serviceLine);

            const dateLine = document.createElement('p');
            dateLine.className = 'text-xs text-slate-500';
            dateLine.textContent = 'Date: ' + event.date + ' | ' + event.start_time + ' - ' + event.end_time;
            leftColumn.appendChild(dateLine);

            const addressLine = document.createElement('p');
            addressLine.className = 'text-xs text-slate-500';
            addressLine.textContent = event.address || 'Address not provided';
            leftColumn.appendChild(addressLine);

            if (hasStaff) {
                const staffLine = document.createElement('p');
                staffLine.className = 'text-xs text-slate-600 mt-1';
                staffLine.textContent = 'Team: ' + event.staff.join(', ');
                leftColumn.appendChild(staffLine);
            }

            const detailButton = document.createElement('button');
            detailButton.type = 'button';
            detailButton.className = 'text-rose-500 hover:text-rose-600 font-semibold text-sm';
            detailButton.textContent = 'View Details';
            detailButton.dataset.action = 'show-booking-detail';
            detailButton.dataset.bookingId = event.id;
            wrapper.appendChild(detailButton); 

            fragment.appendChild(card);
        });

        container.innerHTML = '';
        container.appendChild(fragment);
    }

    // Show Booking Detail Modal
    async function showBookingDetail(bookingId) {
        try {
            const data = await ApiClient.get('api/booking-detail.php', { id: bookingId });
            DashboardState.setBooking(data.booking);
            renderBookingDetailModal(data.booking, data.assigned_staff || []);
        } catch (error) {
            console.error('Failed to load booking details:', error);
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
                        <button data-action="close-detail-modal" class="text-white hover:bg-white hover:bg-opacity-20 rounded-full p-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Customer Info -->
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
                    <!-- Service Info -->
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
                    <!-- Property Details -->
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 mb-3">Property Details</h3>
                        <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl">
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wide">Property Type</p>
                                <p class="font-semibold text-slate-900">${DomUtils.escapeHtml(booking.property.type)}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wide">Bedrooms</p>
                                <p class="text-slate-900">${DomUtils.escapeHtml(booking.property.bedrooms)}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wide">Bathrooms</p>
                                <p class="text-slate-900">${DomUtils.escapeHtml(booking.property.bathrooms)}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wide">Status</p>
                                <p class="text-slate-900 font-semibold" style="color: ${booking.status.color}">${DomUtils.escapeHtml(booking.status.label)}</p>
                            </div>
                        </div>
                    </div>
                    <!-- Assigned Staff -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-semibold text-slate-900">Assigned Staff</h3>
                            <button data-action="show-assign-staff" class="text-sm bg-rose-500 text-white px-4 py-2 rounded-lg hover:bg-rose-600">
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
                                    <button data-action="unassign-staff" data-staff-id="${staff.id}" class="text-red-500 hover:text-red-700 text-sm">
                                        Remove
                                    </button>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <!-- Notes -->
                    ${booking.notes ? `
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900 mb-2">Customer Notes</h3>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p class="text-slate-700">${DomUtils.escapeHtml(booking.notes)}</p>
                            </div>
                        </div>
                    ` : ''}
                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4 border-t border-slate-200">
                        <button data-action="edit-booking" data-booking-id="${booking.id}" class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 font-semibold">
                            Edit Booking
                        </button>
                        <button data-action="cancel-booking" data-booking-id="${booking.id}" class="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 font-semibold">
                            Cancel Booking
                        </button>
                    </div>
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
            DashboardState.setBooking(null);
        }
    }

    async function showAssignStaffModal() {
        if (!DashboardState.currentBooking) return;
        try {
            const time12hr = DashboardState.currentBooking.appointment.time; // "11:00 AM"
            
            const time24hr = convertTime12to24(time12hr); // "11:00:00"
    
            const data = await ApiClient.get(
                'api/assignments.php',
                {
                    date: DashboardState.currentBooking.appointment.date,
                    time: time24hr,
                    booking_id: DashboardState.currentBooking.id
                }
            );
    
            renderAssignStaffModal(data.staff, data.assigned || []);
        }   catch (error) {
            console.error('Failed to load staff assignments:', error);
        }
    }
    
    function convertTime12to24(time12h) {
        if (!time12h || typeof time12h !== 'string') {
            // Fallback for missing or invalid time string, preventing a crash.
            return '00:00:00'; 
        }
        
        const [time, modifier] = time12h.split(' ');
        let [hours, minutes] = time.split(':');
    
        hours = parseInt(hours, 10);
        
        if (modifier === 'PM' && hours !== 12) {
            hours += 12;
        } else if (modifier === 'AM' && hours === 12) {
            hours = 0; // Midnight case
        }
        
        // Use String() and padStart for final robust formatting
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:00`;
    }
    
    function renderAssignStaffModal(staff, assigned) {
        const modal = createModal('assignStaffModal', 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60] p-4');
        
        // Safe staff data handling
        const staffList = Array.isArray(staff) ? staff : [];
        const assignedList = Array.isArray(assigned) ? assigned : [];
        
        modal.innerHTML = `
            <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                <div class="sticky top-0 bg-gradient-to-r from-purple-500 to-indigo-600 text-white p-6 rounded-t-2xl">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-bold">Assign Staff to Booking</h2>
                        <button data-action="close-assign-staff-modal" class="text-white hover:bg-white hover:bg-opacity-20 rounded-full p-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <p class="text-sm text-slate-600 mb-4">Select one or more staff members to assign to this booking.</p>
                    <div class="space-y-3" id="staffSelectionList">
                        ${staffList.length === 0 ? `
                            <div class="text-center py-8 text-slate-500">
                                <p>No staff members available</p>
                            </div>
                        ` : staffList.map(staffMember => {
                            const isAssigned = assignedList.some(a => a.id === staffMember.id);
                            const staffName = staffMember.name || `${staffMember.first_name || ''} ${staffMember.last_name || ''}`.trim();
                            const initials = staffName.split(' ').map(n => n[0] || '').join('').toUpperCase();
                            const colorTag = staffMember.color_tag || '#6b7280';
                            
                            return `
                            <label class="flex items-center justify-between p-4 border-2 rounded-lg cursor-pointer hover:bg-slate-50 transition ${isAssigned ? 'border-green-500 bg-green-50' : 'border-slate-200'}">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox"
                                        name="staff_ids"
                                        value="${DomUtils.escapeHtml(staffMember.id)}"
                                        ${isAssigned ? 'checked disabled' : ''}
                                        class="w-5 h-5 text-rose-500 rounded">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold"
                                        style="background: ${DomUtils.escapeHtml(colorTag)}">
                                        ${DomUtils.escapeHtml(initials)}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-slate-900">${DomUtils.escapeHtml(staffName)}</p>
                                        <p class="text-xs text-slate-500">${DomUtils.escapeHtml(staffMember.role || 'Staff')} | ${staffMember.bookings_count || 0} bookings today</p>
                                    </div>
                                </div>
                                <span class="text-xs px-2 py-1 rounded ${(staffMember.availability_status === 'available') ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}">
                                    ${DomUtils.escapeHtml(staffMember.availability_status || 'unknown')}
                                </span>
                            </label>
                            `;
                        }).join('')}
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button data-action="close-assign-staff-modal" class="flex-1 px-6 py-3 border border-slate-300 rounded-lg hover:bg-slate-50">
                            Cancel
                        </button>
                        <button data-action="submit-assignment" class="flex-1 px-6 py-3 bg-rose-500 text-white rounded-lg hover:bg-rose-600 font-semibold">
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
        if (modal) {
            modal.remove();
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
        }
    }

    // Submit Staff Assignment
    async function submitStaffAssignment() {
        const checkboxes = document.querySelectorAll('input[name="staff_ids"]:checked:not(:disabled)');
        const staffIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
        if (staffIds.length === 0) {
            showNotification('Please select at least one staff member', 'error');
            return;
        }
    
        try {
            
            // Get staff names for success message
            const staffNames = [];
            checkboxes.forEach(checkbox => {
                const staffItem = checkbox.closest('label');
                const nameElement = staffItem.querySelector('.font-semibold.text-slate-900');
                if (nameElement) {
                    staffNames.push(nameElement.textContent.trim());
                }
            });
                
            await ApiClient.post('api/assignments.php', {
                booking_id: DashboardState.currentBooking.id,
                staff_ids: staffIds,
                assignment_role: 'assistant'
            });
    
            //show success message with staff names
            const staffList = hasStaff ? staffNames.join(', ') : ''; // Empty string if no staff
            const message = hasStaff ? `${staffList} successfully assigned! Notification sent` : 'No staff members to assign!';
            showSuccess(message);
            
            // Close both modals
            closeAssignStaffModal();
            closeBookingDetail();
            
            // Refresh the calendar to show updated assignments
            loadCalendar(DashboardState.currentCalendarView, DashboardState.currentCalendarDate);
            
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
                `api/assignments.php?booking_id=${DashboardState.currentBooking.id}&staff_id=${staffId}`
            );
            showSuccess('Staff unassigned successfully');
            closeBookingDetail();
            showBookingDetail(DashboardState.currentBooking.id);
        } catch (error) {
            // Handled by ApiClient
        }
    }

    // Edit Booking Modal
    function showEditBookingModal(bookingId) {
        showNotification(`Edit functionality for ${bookingId} is coming soon!`, 'info');
    }

    // Cancel Booking
    async function cancelBooking(bookingId) {
        if (!confirm('WARNING: Are you absolutely sure you want to CANCEL this booking? This action is often irreversible and requires notifying the client.')) {
            return;
        }
        try {
            await ApiClient.delete(`api/bookings.php?id=${bookingId}`);
            showSuccess('Booking cancelled successfully! Staff assignments removed.');
            
            closeBookingDetail();
            // Reload dashboard/calendar to show the booking removal/status change
            loadCalendar(DashboardState.currentCalendarView, DashboardState.currentCalendarDate);
            loadUpcomingJobs();
        } catch (error) {
            // Handled by ApiClient
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

    function createElement(tag, attributes = {}, children = []) {
        const element = document.createElement(tag);
        Object.keys(attributes).forEach(key => {
            if (key === 'className') {
                element.className = attributes[key];
            } else if (key === 'textContent') {
                element.textContent = attributes[key];
            } else if (key.startsWith('on') && typeof attributes[key] === 'function') {
                const eventName = key.slice(2).toLowerCase();
                element.addEventListener(eventName, attributes[key]);
                DashboardState.eventListeners.push({ element, eventName, handler: attributes[key] });
            } else {
                element.setAttribute(key, attributes[key]);
            }
        });
        children.forEach(child => {
            if (typeof child === 'string') {
                element.appendChild(document.createTextNode(child));
            } else {
                element.appendChild(child);
            }
        });
        return element;
    }

    function updateActiveViewButton(view) {
        document.querySelectorAll('[id^="calendarView"]').forEach(btn => {
            btn.classList.remove('active');
        });
        document.getElementById('calendarView' + view.charAt(0).toUpperCase() + view.slice(1))?.classList.add('active');
    }

    function updateElementText(selector, text) {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = text;
        }
    }

    // Date Picker Setup - With Litepicker fallback
    function setupDatePicker() {
        const picker = document.getElementById('calendarDatePicker');
        if (picker) {
            if (Dependencies.litepicker) {
                // Use Litepicker if available
                new Litepicker({
                    element: picker,
                    singleMode: true,
                    format: 'YYYY-MM-DD',
                    onSelect: (date) => {
                        loadCalendar(DashboardState.currentCalendarView, date.format('YYYY-MM-DD'));
                    }
                });
            } else {
                // Fallback to native date picker
                picker.type = 'date';
                picker.value = DashboardState.currentCalendarDate;
                picker.addEventListener('change', (e) => {
                    loadCalendar(DashboardState.currentCalendarView, e.target.value);
                });
            }
        }
    }

    // Staff Assignment Modal (from main dashboard)
    function showStaffAssignmentModal() {
        showNotification('Please select a booking from the calendar to assign staff.', 'info');
    }

    // Notifications -----------------------------
    function initNotifications() {
        NotificationCenter.listEl = document.getElementById('notificationList');
        NotificationCenter.badgeEl = document.getElementById('notificationBadge');
        NotificationCenter.modalEl = document.getElementById('notificationModal');
        NotificationCenter.toggleEl = document.getElementById('notificationToggle');
        NotificationCenter.closeEl = document.getElementById('closeNotificationModal');
        NotificationCenter.markAllEl = document.getElementById('markAllNotifications');
        NotificationCenter.refreshEl = document.getElementById('refreshNotifications');

        if (!NotificationCenter.toggleEl || !window.ADMIN_CONFIG?.endpoints?.notifications) {
            return;
        }

        NotificationCenter.toggleEl.addEventListener('click', () => toggleNotificationModal(true));
        NotificationCenter.closeEl?.addEventListener('click', () => toggleNotificationModal(false));
        NotificationCenter.markAllEl?.addEventListener('click', markAllNotificationsRead);
        NotificationCenter.refreshEl?.addEventListener('click', loadNotifications);

        NotificationCenter.listEl?.addEventListener('click', (event) => {
            const item = event.target.closest('[data-notification-id]');
            if (!item) {
                return;
            }
            const id = parseInt(item.dataset.notificationId, 10);
            if (Number.isFinite(id)) {
                markNotificationRead(id);
            }
        });

        loadNotifications();
        NotificationCenter.pollTimer = setInterval(loadNotifications, 60000);
    }

    async function loadNotifications() {
        if (!window.ADMIN_CONFIG?.endpoints?.notifications) {
            return;
        }
        try {
            const data = await ApiClient.get(window.ADMIN_CONFIG.endpoints.notifications, { limit: 20 });
            NotificationCenter.notifications = data.notifications || [];
            NotificationCenter.unreadCount = data.unread_count || 0;
            renderNotificationBadge();
            renderNotificationList();
        } catch (error) {
            // ApiClient handles toast
        }
    }

    function renderNotificationBadge() {
        const badge = NotificationCenter.badgeEl;
        if (!badge) {
            return;
        }
        if (NotificationCenter.unreadCount > 0) {
            badge.textContent = Math.min(NotificationCenter.unreadCount, 99).toString();
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    function renderNotificationList() {
        const listEl = NotificationCenter.listEl;
        if (!listEl) {
            return;
        }
        if (NotificationCenter.notifications.length === 0) {
            listEl.innerHTML = '<div class="px-6 py-8 text-center text-slate-400">No notifications yet.</div>';
            return;
        }

        const rows = NotificationCenter.notifications.map((notification) => {
            const isUnread = notification.is_read === false || notification.is_read === 0;
            return `
                <div data-notification-id="${notification.id}" class="px-6 py-4 ${isUnread ? 'bg-rose-50/50' : ''} hover:bg-slate-50 cursor-pointer">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">${DomUtils.escapeHtml(notification.title)}</p>
                            <p class="text-sm text-slate-500 mt-1">${DomUtils.escapeHtml(notification.message)}</p>
                        </div>
                        <span class="text-xs text-slate-400">${notification.created_at}</span>
                    </div>
                </div>
            `;
        });

        listEl.innerHTML = rows.join('');
    }

    function toggleNotificationModal(forceOpen) {
        const modal = NotificationCenter.modalEl;
        if (!modal) {
            return;
        }
        if (forceOpen) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        } else {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    async function markNotificationRead(id) {
        try {
            await ApiClient.post(window.ADMIN_CONFIG.endpoints.notifications, {
                action: 'mark_read',
                id
            });
            NotificationCenter.notifications = NotificationCenter.notifications.map((notification) => (
                notification.id === id ? { ...notification, is_read: true } : notification
            ));
            NotificationCenter.unreadCount = Math.max(NotificationCenter.unreadCount - 1, 0);
            renderNotificationBadge();
            renderNotificationList();
        } catch (error) {
            // handled
        }
    }

    async function markAllNotificationsRead() {
        try {
            await ApiClient.post(window.ADMIN_CONFIG.endpoints.notifications, { action: 'mark_all_read' });
            NotificationCenter.notifications = NotificationCenter.notifications.map((notification) => ({
                ...notification,
                is_read: true
            }));
            NotificationCenter.unreadCount = 0;
            renderNotificationBadge();
            renderNotificationList();
        } catch (error) {
            // handled
        }
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', cleanup);
})();

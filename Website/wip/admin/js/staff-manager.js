// Staff Management JavaScript - Fixed Version
(function () {
    'use strict';

    // Check for required dependencies
    if (!window.AdminUtils) {
        console.error('AdminUtils missing. Staff manager disabled.');
        return;
    }

    var ApiClient = window.AdminUtils.ApiClient;
    var DomUtils = window.AdminUtils.DomUtils;
    var showSuccess = window.AdminUtils.showSuccess;
    var showError = window.AdminUtils.showError;

    var endpoints = window.ADMIN_CONFIG && window.ADMIN_CONFIG.endpoints ? window.ADMIN_CONFIG.endpoints : {};
    var staffEndpoint = endpoints.staff || 'api/staff.php';
    var assignmentsEndpoint = endpoints.assignments || 'api/assignments.php';

    var staff = [];
    var currentPage = 1;
    var itemsPerPage = 10;
    var currentView = 'table';

    var elements = {
        tableBody: document.getElementById('staffTableBody'),
        cardContainer: document.getElementById('staffCardContainer'),
        tableContainer: document.getElementById('staffTableContainer'),
        pagination: document.getElementById('paginationContainer'),
        search: document.getElementById('staffSearch'),
        addBtn: document.getElementById('addStaffModalBtn'),
        modal: document.getElementById('staffModal'),
        form: document.getElementById('staffForm'),
        staffId: document.getElementById('staffId'),
        firstName: document.getElementById('firstName'),
        lastName: document.getElementById('lastName'),
        email: document.getElementById('email'),
        phone: document.getElementById('phone'),
        role: document.getElementById('role'),
        colorTag: document.getElementById('colorTag'),
        notes: document.getElementById('notes'),
        cancelBtn: document.getElementById('cancelModalBtn'),
        switchViewBtn: document.getElementById('switchViewBtn')
    };

    document.addEventListener('DOMContentLoaded', function () {
        loadStaff();
        setupEvents();
    });

    function setupEvents() {
        // Search functionality
        if (elements.search) {
            elements.search.addEventListener('input', debounce(loadStaff, 300));
        }

        // Add staff button
        if (elements.addBtn) {
            elements.addBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openStaffModal('Add New Staff');
            });
        }

        // Cancel button - CRITICAL FIX
        if (elements.cancelBtn) {
            elements.cancelBtn.addEventListener('click', function (e) {
                e.preventDefault();
                closeStaffModal();
                return false;
            });
        }

        // View toggle
        if (elements.switchViewBtn) {
            elements.switchViewBtn.addEventListener('click', toggleView);
        }

        // Form submission
        if (elements.form) {
            elements.form.addEventListener('submit', handleSubmit);
        }

        // Pagination
        if (elements.pagination) {
            elements.pagination.addEventListener('click', function (event) {
                if (event.target.tagName === 'BUTTON' && event.target.dataset.page) {
                    currentPage = parseInt(event.target.dataset.page, 10);
                    renderView();
                    renderPagination();
                }
            });
        }

        // Modal close handlers - CRITICAL FIX
        if (elements.modal) {
            // Close when clicking the X button
            var closeBtn = elements.modal.querySelector('.close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    closeStaffModal();
                    return false;
                });
            }
            
            // Close when clicking outside modal (on backdrop)
            elements.modal.addEventListener('click', function (event) {
                // Only close if clicking directly on the modal backdrop, not its children
                if (event.target.id === 'staffModal') {
                    closeStaffModal();
                }
            });
        }
    
        // Escape key handler
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                // Close main staff modal if open
                if (elements.modal && elements.modal.style.display === 'flex') {
                    closeStaffModal();
                }
                // Close assignments modal if open
                var assignmentsModal = document.getElementById('staffAssignmentsModal');
                if (assignmentsModal && assignmentsModal.style.display === 'flex') {
                    assignmentsModal.remove();
                    document.body.style.overflow = '';
                }
            }
        });

        // Action buttons (edit, delete, assignments) - CRITICAL FIX
        document.addEventListener('click', function (event) {
            var actionEl = event.target.closest('[data-action]');
            if (!actionEl) return;

            var staffId = parseInt(actionEl.dataset.staffId, 10);
            var action = actionEl.dataset.action;

            switch (action) {
                case 'edit-staff':
                    event.preventDefault();
                    editStaff(staffId);
                    break;
                case 'delete-staff':
                    event.preventDefault();
                    deleteStaff(staffId);
                    break;
                case 'assign-booking':
                    event.preventDefault();
                    assignToBooking(staffId);
                    break;
                case 'view-assignments':
                    event.preventDefault();
                    viewAssignments(staffId);
                    break;
                case 'unassign-booking':
                    event.preventDefault();
                    unassignFromBooking(staffId, actionEl.dataset.bookingId);
                    break;
                case 'close-modal':
                    event.preventDefault();
                    var modal = actionEl.closest('.modal');
                    if (modal) {
                        modal.remove();
                        document.body.style.overflow = '';
                    }
                    break;
            }
        });
    }

    function loadStaff() {
        var query = elements.search ? elements.search.value.trim() : '';
        var url = query ? staffEndpoint + '?search=' + encodeURIComponent(query) : staffEndpoint;

        return ApiClient.get(url)
            .then(function (response) {
                staff = response.staff || [];
                currentPage = 1; // Reset to first page on reload
                renderView();
                renderPagination();
                return staff;
            })
            .catch(function (error) {
                console.error('Error loading staff:', error);
                showError('Unable to load staff.');
                if (elements.tableBody) {
                    elements.tableBody.innerHTML = '<tr><td colspan="8" class="px-6 py-4 text-center text-red-500">Failed to load staff.</td></tr>';
                }
            });
    }

    function renderView() {
        if (currentView === 'card') {
            if (elements.tableContainer) {
                elements.tableContainer.classList.add('hidden');
            }
            if (elements.cardContainer) {
                elements.cardContainer.classList.remove('hidden');
            }
            renderCards();
        } else {
            if (elements.cardContainer) {
                elements.cardContainer.classList.add('hidden');
            }
            if (elements.tableContainer) {
                elements.tableContainer.classList.remove('hidden');
            }
            renderTable();
        }
    }

    function renderTable() {
        if (!elements.tableBody) return;

        if (staff.length === 0) {
            elements.tableBody.innerHTML = '<tr><td colspan="8" class="px-6 py-4 text-center text-slate-500">No staff members found.</td></tr>';
            return;
        }

        var start = (currentPage - 1) * itemsPerPage;
        var paginated = staff.slice(start, start + itemsPerPage);

        var html = paginated.map(function (member) {
            var statusClass = member.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800';
            var statusText = member.is_active ? 'Active' : 'Inactive';

            return '<tr class="hover:bg-slate-50">' +
                '<td class="px-6 py-4 whitespace-nowrap">' +
                    '<div class="flex items-center">' +
                        '<div class="h-10 w-10 flex-shrink-0 rounded-full flex items-center justify-center text-white font-medium" style="background-color: ' + (member.color_tag || '#6b7280') + '">' +
                            DomUtils.escapeHtml((member.first_name ? member.first_name[0] : '') + (member.last_name ? member.last_name[0] : '')) +
                        '</div>' +
                        '<div class="ml-4">' +
                            '<div class="text-sm font-medium text-gray-900">' + DomUtils.escapeHtml(member.name || member.first_name + ' ' + member.last_name) + '</div>' +
                            '<div class="text-sm text-gray-500">' + DomUtils.escapeHtml(member.role) + '</div>' +
                        '</div>' +
                    '</div>' +
                '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap">' +
                    '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' + statusClass + '">' +
                        DomUtils.escapeHtml(statusText) +
                    '</span>' +
                '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">' + (member.assignments || 0) + '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + DomUtils.escapeHtml(member.role) + '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + DomUtils.escapeHtml(member.email || '') + '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + DomUtils.escapeHtml(member.phone || '') + '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + DomUtils.escapeHtml(member.next_available || 'Not provided') + '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">' +
                    '<button data-action="assign-booking" data-staff-id="' + member.id + '" class="text-green-600 hover:text-green-900 mr-3">Assign</button>' +
                    '<button data-action="view-assignments" data-staff-id="' + member.id + '" class="text-blue-600 hover:text-blue-900 mr-3">View</button>' +
                    '<button data-action="edit-staff" data-staff-id="' + member.id + '" class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>' +
                    '<button data-action="delete-staff" data-staff-id="' + member.id + '" class="text-red-600 hover:text-red-900">Delete</button>' +
                '</td>' +
            '</tr>';
        }).join('');

        elements.tableBody.innerHTML = html;
    }

    function renderCards() {
        if (!elements.cardContainer) return;

        if (staff.length === 0) {
            elements.cardContainer.innerHTML = '<div class="col-span-full text-center text-slate-500 py-12">No staff members found.</div>';
            return;
        }

        var start = (currentPage - 1) * itemsPerPage;
        var paginated = staff.slice(start, start + itemsPerPage);

        var html = paginated.map(function (member) {
            var initials = (member.first_name ? member.first_name[0] : '') + (member.last_name ? member.last_name[0] : '');
            var statusClass = member.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800';
            var statusText = member.is_active ? 'Active' : 'Inactive';

            return '<div class="staff-card">' +
                '<div class="staff-initials" style="background-color: ' + (member.color_tag || '#6b7280') + '">' +
                    DomUtils.escapeHtml(initials) +
                '</div>' +
                '<h3 class="font-semibold text-lg text-slate-900 mb-1">' + DomUtils.escapeHtml(member.name || member.first_name + ' ' + member.last_name) + '</h3>' +
                '<p class="text-sm text-slate-600 mb-1">' + DomUtils.escapeHtml(member.role) + '</p>' +
                '<p class="text-sm text-slate-500 mb-2">' + DomUtils.escapeHtml(member.email || '') + '</p>' +
                '<p class="text-sm text-slate-500 mb-3">' + DomUtils.escapeHtml(member.phone || '') + '</p>' +
                '<div class="flex justify-between items-center mb-4">' +
                    '<span class="status-badge ' + statusClass + '">' + DomUtils.escapeHtml(statusText) + '</span>' +
                    '<span class="text-xs text-slate-500">' + (member.assignments || 0) + ' assignments</span>' +
                '</div>' +
                '<div class="flex flex-wrap gap-2">' +
                    '<button data-action="assign-booking" data-staff-id="' + member.id + '" class="btn-secondary text-xs text-green-600 border-green-200">Assign</button>' +
                    '<button data-action="view-assignments" data-staff-id="' + member.id + '" class="btn-secondary text-xs">View</button>' +
                    '<button data-action="edit-staff" data-staff-id="' + member.id + '" class="btn-secondary text-xs">Edit</button>' +
                    '<button data-action="delete-staff" data-staff-id="' + member.id + '" class="btn-secondary text-xs text-rose-600 border-rose-200">Delete</button>' +
                '</div>' +
            '</div>';
        }).join('');

        elements.cardContainer.innerHTML = html;
    }

    function renderPagination() {
        if (!elements.pagination) return;

        var totalPages = Math.ceil(staff.length / itemsPerPage);
        if (totalPages <= 1) {
            elements.pagination.innerHTML = '';
            return;
        }

        var buttons = [];
        for (var i = 1; i <= totalPages; i++) {
            buttons.push('<button class="page-btn ' + (i === currentPage ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>');
        }

        elements.pagination.innerHTML = buttons.join('');
    }

    function toggleView() {
        currentView = currentView === 'table' ? 'card' : 'table';
        if (elements.switchViewBtn) {
            elements.switchViewBtn.textContent = currentView === 'card' ? 'Switch to Table View' : 'Switch to Card View';
        }
        renderView();
    }

    function openStaffModal(title) {
        var modalTitle = document.getElementById('modalTitle');
        if (modalTitle) {
            modalTitle.textContent = title;
        }
        if (elements.modal) {
            elements.modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeStaffModal() {
        if (elements.modal) {
            elements.modal.style.display = 'none';
        }
        
        document.body.style.overflow = '';
        
        if (elements.form) {
            elements.form.reset();
        }
        if (elements.staffId) {
            elements.staffId.value = '';
        }
        if (elements.colorTag) {
            elements.colorTag.value = '#6b7280';
        }
    }

    function editStaff(id) {
        var member = staff.find(function (item) { return item.id === id; });
        if (!member) return;

        if (elements.staffId) elements.staffId.value = member.id;
        if (elements.firstName) elements.firstName.value = member.first_name || '';
        if (elements.lastName) elements.lastName.value = member.last_name || '';
        if (elements.email) elements.email.value = member.email || '';
        if (elements.phone) elements.phone.value = member.raw_phone || member.phone || '';
        if (elements.role) elements.role.value = (member.role || 'cleaner').toLowerCase().replace(' ', '_');
        if (elements.colorTag) elements.colorTag.value = member.color_tag || '#6b7280';
        if (elements.notes) elements.notes.value = member.notes || '';

        openStaffModal('Edit Staff');
    }

    function handleSubmit(event) {
        event.preventDefault();
    
        var payload = {
            first_name: elements.firstName ? elements.firstName.value.trim() : '',
            last_name: elements.lastName ? elements.lastName.value.trim() : '',
            email: elements.email ? elements.email.value.trim() : '',
            phone: elements.phone ? elements.phone.value.trim() : '',
            role: elements.role ? elements.role.value : 'cleaner',
            color_tag: elements.colorTag ? elements.colorTag.value : '#6b7280',
            notes: elements.notes ? elements.notes.value.trim() : ''
        };
    
        if (!payload.first_name || !payload.last_name || !payload.email) {
            showError('Name and email are required.');
            return;
        }
    
        var staffId = elements.staffId ? elements.staffId.value : '';
        var promise;
        if (staffId) {
            promise = ApiClient.put(staffEndpoint, {
                id: parseInt(staffId, 10),
                updates: payload
            });
        } else {
            promise = ApiClient.post(staffEndpoint, payload);
        }
    
        promise
            .then(function () {
                showSuccess(staffId ? 'Staff updated successfully' : 'Staff created successfully');
                closeStaffModal();
                // Reload staff data
                return loadStaff();
            })
            .catch(function (error) {
                console.error('Error saving staff:', error);
                showError('Failed to save staff member');
            });
    }
    
    function deleteStaff(id) {
        if (!confirm('Are you sure you want to delete this staff member?')) {
            return;
        }
    
        ApiClient.delete(staffEndpoint + '?id=' + id)
            .then(function () {
                showSuccess('Staff member deleted');
                // Reload staff data
                return loadStaff();
            })
            .catch(function (error) {
                console.error('Error deleting staff:', error);
                showError('Failed to delete staff member');
            });
    }

    function assignToBooking(staffId) {
        var bookingId = prompt('Enter booking ID to assign to this staff member:');
        if (!bookingId) return;

        ApiClient.post(assignmentsEndpoint, {
            booking_id: bookingId.trim(),
            staff_ids: [staffId],
            assignment_role: 'assistant',
            send_notification: true
        })
        .then(function () {
            showSuccess('Staff assigned to booking');
            return loadStaff();
        })
        .catch(function (error) {
            console.error('Error assigning staff:', error);
            showError('Failed to assign staff');
        });
    }

    function viewAssignments(staffId) {
        ApiClient.get(assignmentsEndpoint + '?staff_id=' + staffId)
            .then(function (response) {
                var assignments = response.assignments || [];
                showAssignmentsModal(staffId, assignments);
            })
            .catch(function (error) {
                console.error('Error loading assignments:', error);
                showError('Unable to load assignments');
            });
    }

    function showAssignmentsModal(staffId, assignments) {
        // Remove any existing assignments modal first
        var existingModal = document.getElementById('staffAssignmentsModal');
        if (existingModal) {
            existingModal.remove();
        }

        var modal = document.createElement('div');
        modal.id = 'staffAssignmentsModal';
        modal.className = 'modal';
        modal.style.display = 'flex';
        modal.innerHTML = 
            '<div class="modal-content">' +
                '<span class="close" data-action="close-modal">&times;</span>' +
                '<h2 class="text-xl font-bold mb-4">Staff Assignments</h2>' +
                '<div class="max-h-96 overflow-y-auto">' +
                    (assignments.length === 0 ? 
                        '<p class="text-slate-500 text-center py-8">No assignments found.</p>' :
                        assignments.map(function (assignment) {
                            return '<div class="border-b border-slate-200 py-3">' +
                                '<div class="flex justify-between items-center">' +
                                    '<div>' +
                                        '<p class="font-semibold">Booking: ' + DomUtils.escapeHtml(assignment.booking_id) + '</p>' +
                                        '<p class="text-sm text-slate-600">' + DomUtils.escapeHtml(assignment.appointment_date) + ' at ' + DomUtils.escapeHtml(assignment.appointment_time) + '</p>' +
                                        '<p class="text-sm text-slate-500">' + DomUtils.escapeHtml(assignment.address) + '</p>' +
                                    '</div>' +
                                    '<button data-action="unassign-booking" data-staff-id="' + staffId + '" data-booking-id="' + DomUtils.escapeHtml(assignment.booking_id) + '" class="text-red-600 hover:text-red-800 text-sm">Unassign</button>' +
                                '</div>' +
                            '</div>';
                        }).join('')
                    ) +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';

        // Add event listener for backdrop click
        modal.addEventListener('click', function (e) {
            if (e.target.id === 'staffAssignmentsModal') {
                modal.remove();
                document.body.style.overflow = '';
            }
        });
    }

    function unassignFromBooking(staffId, bookingId) {
        if (!bookingId) return;

        if (!confirm('Are you sure you want to unassign this staff member from booking ' + bookingId + '?')) {
            return;
        }

        ApiClient.delete(assignmentsEndpoint + '?booking_id=' + encodeURIComponent(bookingId) + '&staff_id=' + staffId)
            .then(function () {
                showSuccess('Staff unassigned from booking');
                var modal = document.getElementById('staffAssignmentsModal');
                if (modal) {
                    modal.remove();
                }
                document.body.style.overflow = '';
                return loadStaff();
            })
            .catch(function (error) {
                console.error('Error unassigning staff:', error);
                showError('Failed to unassign staff');
            });
    }

    function debounce(func, wait) {
        var timeout;
        return function () {
            var context = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                func.apply(context, args);
            }, wait);
        };
    }
})();
// customer management js
(function () {
    'use strict';

    // ---- Guard rails ----
    if (!window.AdminUtils || !window.ADMIN_CONFIG || !window.ADMIN_CONFIG.endpoints) {
        console.error('AdminUtils or ADMIN_CONFIG missing. Customer manager disabled.');
        return;
    }

    const ApiClient  = window.AdminUtils.ApiClient;
    const DomUtils   = window.AdminUtils.DomUtils || {};
    const escapeHtml = DomUtils.escapeHtml || (str => {
        if (!str && str !== 0) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    });

    const showSuccess = window.AdminUtils.showSuccess || (msg => console.log(msg));
    const showError   = window.AdminUtils.showError   || (msg => alert(msg));

    // ---- State ----
    let customers    = [];
    let currentPage  = 1;
    const itemsPerPage = 10;

    // ---- DOM cache ----
    const el = {
        tableBody:     document.getElementById('customerTableBody'),
        cards:         document.getElementById('customerCardsContainer'),
        pagination:    document.getElementById('paginationContainer'),
        search:        document.getElementById('customerSearch'),
        addBtn:        document.getElementById('addCustomerModalBtn'),
        modal:         document.getElementById('customerModal'),
        form:          document.getElementById('customerForm'),
        customerId:    document.getElementById('customerId'),
        firstName:     document.getElementById('firstName'),
        lastName:      document.getElementById('lastName'),
        email:         document.getElementById('email'),
        phone:         document.getElementById('phone'),
        address:       document.getElementById('address'),
        city:          document.getElementById('city'),
        state:         document.getElementById('state'),
        notes:         document.getElementById('notes'),
        cancelBtn:     document.getElementById('cancelModalBtn'),
        selectAll:     document.getElementById('selectAll'),
        total:         document.getElementById('totalCustomers'),
        new:           document.getElementById('newCustomers'),
        active:        document.getElementById('activeCustomers'),
        churn:         document.getElementById('churnRate'),
        detailModal:   document.getElementById('customerDetailModal'),
        detailContent: document.getElementById('customerDetailContent'),
        modalTitle:    document.getElementById('modalTitle'),
        loader:        document.getElementById('globalLoader')
    };

    // ---- Init ----
    document.addEventListener('DOMContentLoaded', () => {
        setupEvents();
        loadCustomers();
    });

    // ---- Events ----
    function setupEvents() {
        // Search (debounced)
        if (el.search) {
            el.search.addEventListener('input', debounce(() => {
                currentPage = 1;
                loadCustomers();
            }, 300));
        }

        // Add button
        if (el.addBtn) {
            el.addBtn.addEventListener('click', () => openCustomerModal('Add New Customer'));
        }

        // Cancel in modal
        if (el.cancelBtn) {
            el.cancelBtn.addEventListener('click', (e) => {
                e.preventDefault();
                closeCustomerModal();
            });
        }

        // Form submit
        if (el.form) {
            el.form.addEventListener('submit', handleCustomerFormSubmit);
        }

        // Main modal close
        if (el.modal) {
            const closeBtn = el.modal.querySelector('.close');
            if (closeBtn) {
                closeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    closeCustomerModal();
                });
            }
            el.modal.addEventListener('click', (event) => {
                if (event.target === el.modal) closeCustomerModal();
            });
        }

        // Esc to close modals
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' || event.keyCode === 27) {
                if (el.modal && el.modal.style.display === 'flex') closeCustomerModal();
                if (el.detailModal && el.detailModal.style.display === 'flex') closeCustomerDetailModal();
            }
        });

        // Select all checkboxes (desktop)
        if (el.selectAll && el.tableBody) {
            el.selectAll.addEventListener('change', function () {
                const boxes = el.tableBody.querySelectorAll('input.customer-checkbox[type="checkbox"]');
                boxes.forEach(cb => { cb.checked = this.checked; });
            });
        }

        // Global click actions (works for both table + cards)
        document.addEventListener('click', (event) => {
            // Detail backdrop
            if (el.detailModal && event.target === el.detailModal) {
                closeCustomerDetailModal();
                return;
            }

            const btn = event.target.closest('[data-action]');
            if (!btn) return;

            const action     = btn.dataset.action;
            const customerId = btn.dataset.customerId;

            switch (action) {
                case 'show-customer-detail':
                    event.preventDefault();
                    if (customerId) showCustomerDetail(customerId);
                    break;
                case 'edit-customer':
                    event.preventDefault();
                    if (customerId) editCustomer(customerId);
                    break;
                case 'delete-customer':
                    event.preventDefault();
                    if (customerId) deleteCustomer(customerId);
                    break;
                case 'close-detail':
                    event.preventDefault();
                    closeCustomerDetailModal();
                    break;
            }
        });
    }

    // ---- Load data ----
    function buildEndpoint(page, searchQuery) {
        const base = window.ADMIN_CONFIG.endpoints.customers;
        const params = new URLSearchParams();
        if (page) params.append('page', String(page));
        if (searchQuery) params.append('search', searchQuery);
        const qs = params.toString();
        return qs ? `${base}?${qs}` : base;
    }

    function loadCustomers(page) {
        const pageToLoad = page || currentPage;
        const query = el.search ? el.search.value.trim() : '';

        showLoader();
        const endpoint = buildEndpoint(pageToLoad, query);

        return ApiClient.get(endpoint)
            .then((response) => {
                const list =
                    (response && (response.customers || response.data)) ||
                    (Array.isArray(response) ? response : []);

                customers = list || [];
                currentPage = pageToLoad;

                if (response && response.stats) {
                    updateStatsFromApi(response.stats);
                } else {
                    updateStatsFromCustomers();
                }

                renderCustomers();
                renderPagination(response && response.pagination);
            })
            .catch((err) => {
                console.error('Error loading customers:', err);
                if (el.tableBody) {
                    el.tableBody.innerHTML =
                        '<tr><td colspan="9" class="px-6 py-4 text-center text-red-500">Failed to load customers.</td></tr>';
                }
                if (el.cards) {
                    el.cards.innerHTML =
                        '<div class="text-center py-8 text-red-500 text-sm">Failed to load customers.</div>';
                }
                showError('Unable to load customers.');
            })
            .finally(hideLoader);
    }

    // ---- Stats ----
    function updateStatsFromApi(stats) {
        if (el.total)  el.total.textContent  = (stats.total || 0).toLocaleString();
        if (el.new)    el.new.textContent    = (stats.new || 0).toLocaleString();
        if (el.active) el.active.textContent = (stats.active || 0).toLocaleString();

        const churnVal = typeof stats.churnRate === 'number'
            ? stats.churnRate.toFixed(1) + '%'
            : (stats.churnRate || '0.0%');

        if (el.churn) el.churn.textContent = churnVal;
    }

    function updateStatsFromCustomers() {
        const total = customers.length;
        let activeCount = 0;
        let newThisMonth = 0;

        const now = Date.now();
        const thirtyDays = 30 * 24 * 60 * 60 * 1000;

        customers.forEach((c) => {
            if (c.last_booking) activeCount++;

            const created = c.created_at || c.registration_date;
            if (created) {
                const t = new Date(created).getTime();
                if (!isNaN(t) && now - t <= thirtyDays) newThisMonth++;
            }
        });

        if (el.total)  el.total.textContent  = total.toLocaleString();
        if (el.new)    el.new.textContent    = newThisMonth.toLocaleString();
        if (el.active) el.active.textContent = activeCount.toLocaleString();

        const churnRate = total
            ? (((total - activeCount) / total) * 100).toFixed(1) + '%'
            : '0.0%';
        if (el.churn) el.churn.textContent = churnRate;
    }

    // ---- Renderers (desktop + mobile) ----
    function getPageCustomers() {
        const start = (currentPage - 1) * itemsPerPage;
        const end   = start + itemsPerPage;
        return customers.slice(start, end);
    }

    function renderCustomers() {
        const pageCustomers = getPageCustomers();
        renderTable(pageCustomers);
        renderCards(pageCustomers);
    }

    function renderTable(list) {
        if (!el.tableBody) return;

        if (!list || list.length === 0) {
            el.tableBody.innerHTML =
                '<tr><td colspan="9" class="px-6 py-4 text-center text-slate-500">No customers found.</td></tr>';
            return;
        }

        el.tableBody.innerHTML = list.map((customer) => {
            const fullName =
                customer.full_name ||
                `${customer.first_name || ''} ${customer.last_name || ''}`.trim() ||
                '---';

            const status = customer.status || (customer.last_booking ? 'active' : 'inactive');
            const isActive = status === 'active';

            const statusClass = isActive
                ? 'bg-green-100 text-green-800'
                : 'bg-gray-100 text-gray-800';
            const statusText = isActive ? 'Active' : 'Inactive';

            const createdAt = customer.created_at || customer.registration_date;
            const lastOrder = customer.last_order_date || customer.last_booking;

            const totalOrders = customer.total_orders || 0;
            const totalSpent  = parseFloat(customer.total_spent || 0).toFixed(2);

            return `
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <input type="checkbox" class="customer-checkbox mr-2" data-id="${escapeHtml(customer.id)}">
                        <a href="#" 
                           data-action="show-customer-detail" 
                           data-customer-id="${escapeHtml(customer.id)}" 
                           class="font-medium text-blue-600 hover:text-blue-900">
                            ${escapeHtml(fullName)}
                        </a>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${escapeHtml(customer.email || '---')}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${escapeHtml(customer.customer_id || customer.id)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${escapeHtml(formatDate(createdAt))}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${escapeHtml(lastOrder ? formatDate(lastOrder) : 'N/A')}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${totalOrders}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        $${totalSpent}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                            ${statusText}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right">
                        <button 
                            data-action="show-customer-detail" 
                            data-customer-id="${escapeHtml(customer.id)}" 
                            class="text-blue-600 hover:text-blue-900 mr-3">
                            Details
                        </button>
                        <button 
                            data-action="edit-customer" 
                            data-customer-id="${escapeHtml(customer.id)}" 
                            class="text-gray-600 hover:text-gray-900 mr-3">
                            Edit
                        </button>
                        <button 
                            data-action="delete-customer" 
                            data-customer-id="${escapeHtml(customer.id)}" 
                            class="text-red-600 hover:text-red-900">
                            Delete
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderCards(list) {
        if (!el.cards) return;

        if (!list || list.length === 0) {
            el.cards.innerHTML =
                '<div class="text-center py-8 text-slate-500 text-sm">No customers found</div>';
            return;
        }

        el.cards.innerHTML = list.map((customer) => {
            const fullName =
                customer.full_name ||
                `${customer.first_name || ''} ${customer.last_name || ''}`.trim() ||
                '---';

            const status = customer.status || (customer.last_booking ? 'active' : 'inactive');
            const isActive = status === 'active';
            const statusClass = isActive ? 'status-active' : 'status-inactive';
            const statusText  = isActive ? 'Active' : 'Inactive';

            const createdAt = customer.created_at || customer.registration_date;
            const lastOrder = customer.last_order_date || customer.last_booking;

            const totalOrders = customer.total_orders || 0;
            const totalSpent  = parseFloat(customer.total_spent || 0).toFixed(2);

            return `
                <div class="customer-card">
                    <div class="customer-card-header">
                        <div>
                            <div class="customer-card-name">
                                ${escapeHtml(fullName)}
                            </div>
                            <div class="customer-card-id">
                                ID: ${escapeHtml(customer.customer_id || customer.id)}
                            </div>
                        </div>
                        <span class="customer-card-status ${statusClass}">${statusText}</span>
                    </div>

                    <div class="customer-card-info">
                        <div class="customer-card-field">
                            <div class="customer-card-label">Email</div>
                            <div class="customer-card-value">
                                ${escapeHtml(customer.email || '---')}
                            </div>
                        </div>
                        <div class="customer-card-field">
                            <div class="customer-card-label">Phone</div>
                            <div class="customer-card-value">
                                ${escapeHtml(customer.phone || 'N/A')}
                            </div>
                        </div>
                        <div class="customer-card-field">
                            <div class="customer-card-label">Registration</div>
                            <div class="customer-card-value">
                                ${escapeHtml(formatDate(createdAt))}
                            </div>
                        </div>
                        <div class="customer-card-field">
                            <div class="customer-card-label">Last Order</div>
                            <div class="customer-card-value">
                                ${escapeHtml(lastOrder ? formatDate(lastOrder) : 'N/A')}
                            </div>
                        </div>
                        <div class="customer-card-field">
                            <div class="customer-card-label">Total Orders</div>
                            <div class="customer-card-value">
                                ${totalOrders}
                            </div>
                        </div>
                        <div class="customer-card-field">
                            <div class="customer-card-label">Total Spent</div>
                            <div class="customer-card-value">
                                $${totalSpent}
                            </div>
                        </div>
                    </div>

                    <div class="customer-card-actions">
                        <button 
                            class="card-action-btn card-action-view" 
                            data-action="show-customer-detail" 
                            data-customer-id="${escapeHtml(customer.id)}">
                            <i class="fas fa-eye mr-1"></i> View
                        </button>
                        <button 
                            class="card-action-btn card-action-edit" 
                            data-action="edit-customer" 
                            data-customer-id="${escapeHtml(customer.id)}">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    // ---- Detail modal ----
    function showCustomerDetail(id) {
        const customer = customers.find(c => String(c.id) === String(id));
        if (!customer) return;

        const url = window.ADMIN_CONFIG.endpoints.booking + '?customer_id=' + encodeURIComponent(id);

        showLoader();
        ApiClient.get(url)
            .then((response) => {
                const bookings = (response && response.bookings) || [];
                let bookingsHTML = '<p class="text-slate-500">No bookings found.</p>';

                if (bookings.length > 0) {
                    bookingsHTML = '<div class="space-y-4">';
                    bookings.forEach((b) => {
                        const serviceName = getServiceName(b.service_type) || b.service_type;
                        const statusColor = getStatusColor(b.status);
                        const statusTextColor = getStatusTextColor(b.status);
                        const statusLabel = getStatusLabel(b.status);

                        let staffDisplay = 'Unassigned';
                        if (b.staff && b.staff.length) {
                            staffDisplay = b.staff.map(s => escapeHtml(s)).join(', ');
                        }

                        bookingsHTML += `
                            <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-semibold">${escapeHtml(serviceName)}</h4>
                                        <p class="text-sm text-slate-600">
                                            ${escapeHtml(b.appointment_date || '')} at ${escapeHtml(b.appointment_time || '')}
                                        </p>
                                        <p class="text-sm text-slate-500">
                                            ${escapeHtml(b.address || '---')}
                                        </p>
                                    </div>
                                    <span class="px-2 py-1 rounded text-xs font-medium"
                                          style="background-color: ${statusColor}20; color: ${statusTextColor}">
                                        ${escapeHtml(statusLabel)}
                                    </span>
                                </div>
                                <div class="mt-2 text-sm">
                                    <strong>Team:</strong> ${staffDisplay}
                                </div>
                            </div>
                        `;
                    });
                    bookingsHTML += '</div>';
                }

                const fullName =
                    customer.full_name ||
                    customer.name ||
                    `${customer.first_name || ''} ${customer.last_name || ''}`.trim() ||
                    '---';

                const detailHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h4 class="font-semibold mb-2">Personal Info</h4>
                            <p><strong>Name:</strong> ${escapeHtml(fullName)}</p>
                            <p><strong>Email:</strong> ${escapeHtml(customer.email || '---')}</p>
                            <p><strong>Phone:</strong> ${escapeHtml(customer.phone || '---')}</p>
                            <p><strong>Location:</strong> ${escapeHtml(customer.city || '---')}</p>
                        </div>
                        <div>
                            <h4 class="font-semibold mb-2">Account Info</h4>
                            <p><strong>ID:</strong> ${escapeHtml(customer.id)}</p>
                            <p><strong>Status:</strong> ${
                                customer.last_booking
                                    ? '<span class="text-green-600">Active</span>'
                                    : '<span class="text-gray-500">Inactive</span>'
                            }</p>
                            <p><strong>Last Booking:</strong> ${escapeHtml(customer.last_booking || 'Never')}</p>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-3">Booking History (${bookings.length})</h4>
                        ${bookingsHTML}
                    </div>
                `;

                if (el.detailContent) el.detailContent.innerHTML = detailHTML;

                if (el.detailModal) {
                    el.detailModal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                    document.body.classList.add('modal-open');
                }
            })
            .catch((err) => {
                console.error('Error loading customer detail:', err);
                showError('Failed to load customer details.');
            })
            .finally(hideLoader);
    }

    function closeCustomerDetailModal() {
        if (el.detailModal) el.detailModal.style.display = 'none';
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
    }

    // ---- CRUD helpers ----
    function editCustomer(id) {
        const customer = customers.find(c => String(c.id) === String(id));
        if (!customer) return;

        if (el.customerId) el.customerId.value = customer.id;
        if (el.firstName)  el.firstName.value  = customer.first_name || '';
        if (el.lastName)   el.lastName.value   = customer.last_name || '';
        if (el.email)      el.email.value      = customer.email || '';
        if (el.phone)      el.phone.value      = customer.raw_phone || customer.phone || '';
        if (el.city)       el.city.value       = customer.city || '';
        if (el.state)      el.state.value      = customer.state || '';
        if (el.notes)      el.notes.value      = customer.notes || '';
        if (el.address)    el.address.value    = customer.address || '';

        openCustomerModal('Edit Customer');
    }

    function handleCustomerFormSubmit(e) {
        e.preventDefault();

        const payload = {
            first_name: el.firstName ? el.firstName.value.trim() : '',
            last_name:  el.lastName  ? el.lastName.value.trim()  : '',
            email:      el.email     ? el.email.value.trim()     : '',
            phone:      el.phone     ? el.phone.value.trim()     : '',
            address:    el.address   ? el.address.value.trim()   : '',
            city:       el.city      ? el.city.value.trim()      : '',
            state:      el.state     ? el.state.value.trim()     : '',
            notes:      el.notes     ? el.notes.value.trim()     : ''
        };

        if (!payload.first_name || !payload.last_name || !payload.email) {
            showError('Name and email are required.');
            return;
        }

        const customerId = el.customerId ? el.customerId.value : '';
        let promise;

        showLoader();

        if (customerId) {
            promise = ApiClient.put(window.ADMIN_CONFIG.endpoints.customers, {
                id: parseInt(customerId, 10),
                updates: payload
            });
        } else {
            promise = ApiClient.post(window.ADMIN_CONFIG.endpoints.customers, payload);
        }

        promise
            .then((response) => {
                if (response && response.success) {
                    showSuccess(customerId ? 'Customer updated' : 'Customer created');
                    closeCustomerModal();
                    return loadCustomers();
                }
                throw new Error(response ? response.message : 'Operation failed');
            })
            .catch((err) => {
                console.error('Error saving customer:', err);
                showError('Failed to save customer: ' + (err.message || 'Unknown error'));
            })
            .finally(hideLoader);
    }

    function deleteCustomer(id) {
        if (!id) return;
        if (!window.confirm('Delete this customer? This cannot be undone.')) return;

        showLoader();
        ApiClient.delete(window.ADMIN_CONFIG.endpoints.customers + '?id=' + encodeURIComponent(id))
            .then((response) => {
                if (response && response.success) {
                    showSuccess('Customer deleted');
                    return loadCustomers();
                }
                throw new Error(response ? response.message : 'Delete failed');
            })
            .catch((err) => {
                console.error('Error deleting customer:', err);
                showError('Failed to delete customer: ' + (err.message || 'Unknown error'));
            })
            .finally(hideLoader);
    }

    // ---- Modal helpers ----
    function openCustomerModal(title) {
        if (el.modalTitle) el.modalTitle.textContent = title || 'Customer';

        if (el.modal) {
            el.modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            document.body.classList.add('modal-open');
        }
    }

    function closeCustomerModal() {
        if (el.modal) el.modal.style.display = 'none';
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
        if (el.form) el.form.reset();
        if (el.customerId) el.customerId.value = '';
    }

    // ---- Pagination ----
    function renderPagination(serverPagination) {
        if (!el.pagination) return;

        let totalPages;
        if (serverPagination && serverPagination.totalPages) {
            totalPages = serverPagination.totalPages;
        } else {
            totalPages = Math.ceil(customers.length / itemsPerPage);
        }

        if (totalPages <= 1) {
            el.pagination.innerHTML = '';
            return;
        }

        const parts = [];

        // Prev
        parts.push(`
            <button class="page-btn ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''}" 
                    data-page="${Math.max(1, currentPage - 1)}">
                Prev
            </button>
        `);

        for (let i = 1; i <= totalPages; i++) {
            parts.push(`
                <button class="page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">
                    ${i}
                </button>
            `);
        }

        // Next
        parts.push(`
            <button class="page-btn ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : ''}" 
                    data-page="${Math.min(totalPages, currentPage + 1)}">
                Next
            </button>
        `);

        el.pagination.innerHTML = parts.join('');

        const buttons = el.pagination.querySelectorAll('button[data-page]');
        buttons.forEach((btn) => {
            btn.addEventListener('click', function () {
                if (this.classList.contains('opacity-50')) return;
                const page = parseInt(this.dataset.page, 10) || 1;
                currentPage = page;

                // If server controls pagination, reload; otherwise just re-render
                if (serverPagination && serverPagination.totalPages) {
                    loadCustomers(page);
                } else {
                    renderCustomers();
                    renderPagination();
                }
            });
        });
    }

    // ---- Util ----
    function debounce(fn, wait) {
        let timeout;
        return function () {
            const ctx = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(ctx, args), wait);
        };
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'N/A';
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function showLoader() {
        if (el.loader) el.loader.style.display = 'flex';
    }

    function hideLoader() {
        if (el.loader) el.loader.style.display = 'none';
    }

    // ---- Booking helpers ----
    function getServiceName(code) {
        const catalog = {
            regular: 'Standard Cleaning',
            deep: 'Deep Cleaning',
            move: 'Move In/Out',
            onetime: 'One-Time Cleaning'
        };
        return catalog[code] || code || 'Unknown';
    }

    function getStatusColor(status) {
        const map = {
            pending: '#f59e0b',
            confirmed: '#22d3ee',
            completed: '#22c55e',
            cancelled: '#ef4444'
        };
        return map[status] || '#a855f7';
    }

    function getStatusTextColor(status) {
        return status === 'cancelled' ? '#ef4444' : '#6b7280';
    }

    function getStatusLabel(status) {
        if (!status) return 'Unknown';
        return status.charAt(0).toUpperCase() + status.slice(1);
    }

    // Optional: expose for quick debug
    window.CustomerManager = {
        reload: loadCustomers
    };
})();

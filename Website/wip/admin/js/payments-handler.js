(function() {
    'use strict';

    var ApiClient = window.AdminUtils.ApiClient;
    var DomUtils = window.AdminUtils.DomUtils;
    var showSuccess = window.AdminUtils.showSuccess;
    var showError = window.AdminUtils.showError;

    var invoices = [];

    // Single DOMContentLoaded event listener
    document.addEventListener('DOMContentLoaded', function() {
        loadInvoices();
        bindEvents();
    });

    function loadInvoices() {
        var statusFilter = document.getElementById('statusFilter');
        var status = statusFilter ? statusFilter.value : '';
        var endpoint = window.ADMIN_CONFIG.endpoints.invoices;
        
        if (status) {
            endpoint += '?status=' + encodeURIComponent(status);
        }

        ApiClient.get(endpoint)
            .then(function(response) {
                if (response && response.success) {
                    invoices = response.invoices || [];
                    renderInvoices();
                    updateStats();
                }
            })
            .catch(function(error) {
                console.error('Failed to load invoices:', error);
                showError('Failed to load invoices. Please try again.');
            });
    }

    function updateStats() {
        var totalRevenue = 0;
        var pending = 0;
        var overdue = 0;
        var paidThisMonth = 0;
        var now = new Date();
        var currentMonth = now.getMonth();
        var currentYear = now.getFullYear();

        for (var i = 0; i < invoices.length; i++) {
            var inv = invoices[i];
            
            if (inv.status === 'paid') {
                // Parse the formatted total (remove $ and commas)
                var amount = parseFloat(inv.total.replace(/[$,]/g, ''));
                if (!isNaN(amount)) {
                    totalRevenue += amount;
                }
                
                // Check if paid this month (if issue_date is available)
                if (inv.issue_date) {
                    var issueDate = new Date(inv.issue_date);
                    if (issueDate.getMonth() === currentMonth && 
                        issueDate.getFullYear() === currentYear) {
                        paidThisMonth++;
                    }
                }
            } else if (inv.status === 'pending') {
                pending++;
            } else if (inv.status === 'overdue') {
                overdue++;
            }
        }

        // Safely update DOM elements with null checks
        var totalRevenueEl = document.getElementById('totalRevenue');
        var pendingInvoicesEl = document.getElementById('pendingInvoices');
        var overdueInvoicesEl = document.getElementById('overdueInvoices');
        var paidThisMonthEl = document.getElementById('paidThisMonth');

        if (totalRevenueEl) {
            totalRevenueEl.textContent = '$' + totalRevenue.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        if (pendingInvoicesEl) pendingInvoicesEl.textContent = pending;
        if (overdueInvoicesEl) overdueInvoicesEl.textContent = overdue;
        if (paidThisMonthEl) paidThisMonthEl.textContent = paidThisMonth;
    }

    function renderInvoices() {
        var container = document.getElementById('invoicesContainer');
        
        if (!container) return;

        if (!invoices.length) {
            container.innerHTML = '<div class="invoice-card text-center py-12">' +
                '<p class="text-gray-500 text-lg">No invoices found</p>' +
                '<p class="text-gray-400 text-sm">Try adjusting your filters</p>' +
                '</div>';
            return;
        }

        var html = '';
        for (var i = 0; i < invoices.length; i++) {
            var inv = invoices[i];
            var statusClass = 'status-' + inv.status;
            
            html += '<div class="invoice-card">' +
                '<div class="flex items-center justify-between mb-4">' +
                    '<div>' +
                        '<h3 class="font-semibold text-lg">Invoice #' + DomUtils.escapeHtml(inv.invoice_number) + '</h3>' +
                        '<p class="text-gray-600 text-sm">' + DomUtils.escapeHtml(inv.customer_name) + '</p>' +
                    '</div>' +
                    '<div class="text-right">' +
                        '<div class="text-xl font-bold">' + DomUtils.escapeHtml(inv.total) + '</div>' +
                        '<span class="status-badge ' + statusClass + '">' + DomUtils.escapeHtml(inv.status.toUpperCase()) + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600 mb-4">' +
                    '<div><strong>Booking ID:</strong> ' + DomUtils.escapeHtml(inv.booking_id || 'N/A') + '</div>' +
                    '<div><strong>Issue Date:</strong> ' + DomUtils.escapeHtml(inv.issue_date) + '</div>' +
                    '<div><strong>Status:</strong> ' + DomUtils.escapeHtml(inv.status) + '</div>' +
                '</div>' +
            '</div>';
        }
        
        container.innerHTML = html;
    }

    function bindEvents() {
        var createBtn = document.getElementById('createInvoiceBtn');
        if (createBtn) {
            createBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openModal();
            });
        }
    
        var statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', function() {
                loadInvoices();
            });
        }
    
        // Modal handlers
        var modal = document.getElementById('invoiceModal');
        if (modal) {
            var closeBtn = modal.querySelector('.close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeModal();
                });
            }
    
            var cancelBtn = document.getElementById('cancelInvoiceBtn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeModal();
                });
            }
    
            // Close on backdrop click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
        }
    
        // Form submission
        var form = document.getElementById('invoiceForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                handleInvoiceSubmit();
            });
        }

        // Booking selection handler
        var bookingSelect = document.getElementById('bookingSelect');
        if (bookingSelect) {
            bookingSelect.addEventListener('change', function() {
                var selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    var price = selectedOption.getAttribute('data-price');
                    var customer = selectedOption.getAttribute('data-customer');
                    
                    // Auto-populate fields
                    var amountInput = document.getElementById('invoiceAmount');
                    var customerInput = document.getElementById('customerName');
                    
                    if (amountInput && price) {
                        amountInput.value = price;
                    }
                    if (customerInput && customer) {
                        customerInput.value = customer;
                    }
                }
            });
        }
    }
    
    function openModal() {
        var modal = document.getElementById('invoiceModal');
        if (modal) {
            // Auto-generate invoice number
            var invoiceNumberInput = document.getElementById('invoiceNumber');
            if (invoiceNumberInput) {
                invoiceNumberInput.value = generateInvoiceNumber();
            }
            
            // Load bookings for dropdown
            loadBookingsForInvoice();
            
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeModal() {
        var modal = document.getElementById('invoiceModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
        var form = document.getElementById('invoiceForm');
        if (form) {
            form.reset();
        }
    }
    
    function generateInvoiceNumber() {
        var timestamp = Date.now().toString(36).toUpperCase();
        var random = Math.random().toString(36).substring(2, 6).toUpperCase();
        return 'INV-' + timestamp + random;
    }
    
    function loadBookingsForInvoice() {
        ApiClient.get(window.ADMIN_CONFIG.endpoints.bookings + '?status=completed&limit=50')
            .then(function(response) {
                if (response && response.success) {
                    var select = document.getElementById('bookingSelect');
                    if (select && response.bookings) {
                        select.innerHTML = '<option value="">Select a booking</option>';
                        response.bookings.forEach(function(booking) {
                            var option = document.createElement('option');
                            var price = booking.final_price || booking.estimated_price || '0';
                            var fullName = booking.first_name + ' ' + booking.last_name;
                            
                            option.value = booking.booking_id;
                            option.textContent = '#' + booking.booking_id + ' - ' + fullName + 
                                               ' - ' + booking.service_type + ' - $' + price;
                            option.setAttribute('data-price', price);
                            option.setAttribute('data-customer', fullName);
                            select.appendChild(option);
                        });
                    }
                }
            })
            .catch(function(error) {
                console.error('Failed to load bookings:', error);
                showError('Failed to load bookings for invoice creation.');
            });
    }

    function handleInvoiceSubmit() {
        // Collect form data
        var formData = {
            invoice_number: document.getElementById('invoiceNumber').value,
            booking_id: document.getElementById('bookingSelect').value,
            customer_name: document.getElementById('customerName').value,
            amount: document.getElementById('invoiceAmount').value,
            issue_date: document.getElementById('issueDate').value,
            status: document.getElementById('invoiceStatus').value
        };

        // Validate required fields
        if (!formData.invoice_number || !formData.customer_name || !formData.amount) {
            showError('Please fill in all required fields');
            return;
        }

        // API call to create invoice
        ApiClient.post(window.ADMIN_CONFIG.endpoints.invoices, formData)
            .then(function(response) {
                if (response && response.success) {
                    showSuccess('Invoice created successfully!');
                    closeModal();
                    loadInvoices(); // Reload the invoice list
                } else {
                    showError('Failed to create invoice: ' + (response.message || 'Unknown error'));
                }
            })
            .catch(function(error) {
                console.error('Failed to create invoice:', error);
                showError('Failed to create invoice. Please try again.');
            });
    }

})();
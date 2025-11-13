(function() {
    'use strict';

    var ApiClient = window.AdminUtils.ApiClient;
    var DomUtils = window.AdminUtils.DomUtils;
    var showSuccess = window.AdminUtils.showSuccess;
    var showError = window.AdminUtils.showError;

    document.addEventListener('DOMContentLoaded', function() {
        loadReportData();
        bindEvents();
    });

    function loadReportData() {
        ApiClient.get(window.ADMIN_CONFIG.endpoints.reports)
            .then(function(response) {
                if (response && response.success) {
                    updateStats(response);
                }
            })
            .catch(function(error) {
                console.error('Failed to load report:', error);
            });
    }

    function updateStats(data) {
        document.getElementById('totalBookings').textContent = data.weekly_bookings || 0;
        document.getElementById('totalRevenue').textContent = data.monthly_revenue || '$0.00';
        
        var total = (data.scheduled_count || 0) + (data.in_progress_count || 0) + 
                    (data.completed_count || 0) + (data.cancelled_count || 0);
        var completionRate = total > 0 ? ((data.completed_count || 0) / total * 100).toFixed(1) : '0.0';
        
        document.getElementById('avgBookingValue').textContent = '$0.00';
        document.getElementById('completionRate').textContent = completionRate + '%';
    }

    function openExportModal() {
        console.log('openExportModal called'); // Debug line
        var modal = document.getElementById('exportModal');
        if (modal) {
            modal.style.display = 'flex'; // Changed to flex
            document.body.style.overflow = 'hidden';
            document.body.classList.add('modal-open');
        } else {
            console.error('Export modal not found');
        }
    }

    function closeExportModal() {
        var modal = document.getElementById('exportModal');
        if (modal) {
            modal.style.display = 'none';
        }
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
        
        var form = document.getElementById('exportForm');
        if (form) {
            form.reset();
        }
    }

    function handleExport(formData) {
        var format = formData.get('format');
        
        if (!format) {
            showError('Please select an export format');
            return;
        }
    
        var reportType = document.getElementById('reportType').value;
        var dateFrom = document.getElementById('dateFrom').value;
        var dateTo = document.getElementById('dateTo').value;
        
        var params = new URLSearchParams({
            format: format,
            report_type: reportType,
            date_from: dateFrom,
            date_to: dateTo
        });
    
        // Fixed: Use correct endpoint without '/export'
        var url = window.ADMIN_CONFIG.endpoints.reports + '?' + params.toString();
        
        showSuccess('Preparing ' + format.toUpperCase() + ' export...');
        closeExportModal();
        
        // Create hidden iframe to trigger download without opening new tab
        var iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = url;
        document.body.appendChild(iframe);
        
        // Remove iframe after download starts
        setTimeout(function() {
            document.body.removeChild(iframe);
        }, 5000);
    }
    
    function bindEvents() {
        var generateBtn = document.getElementById('generateReportBtn');
        if (generateBtn) {
            generateBtn.addEventListener('click', function(e) {
                e.preventDefault();
                loadReportData();
            });
        }

        var exportBtn = document.getElementById('exportReportBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openExportModal();
            });
        }

        // Export modal handlers
        var exportModal = document.getElementById('exportModal');
        if (exportModal) {
            var closeBtn = exportModal.querySelector('.close[data-modal="export"]');
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeExportModal();
                });
            }

            var cancelBtn = document.getElementById('cancelExportBtn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeExportModal();
                });
            }

            exportModal.addEventListener('click', function(e) {
                if (e.target === exportModal) {
                    closeExportModal();
                }
            });
        }

        // Export form submission
        var exportForm = document.getElementById('exportForm');
        if (exportForm) {
            exportForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(e.target);
                handleExport(formData);
            });
        }

        // Tab buttons
        var tabButtons = document.querySelectorAll('.tab-button');
        
        function handleTabClick(e) {
            e.preventDefault();
            var allTabs = document.querySelectorAll('.tab-button');
            for (var j = 0; j < allTabs.length; j++) {
                allTabs[j].classList.remove('active');
            }
            e.target.classList.add('active');
            loadReportData();
        }

        for (var i = 0; i < tabButtons.length; i++) {
            tabButtons[i].addEventListener('click', handleTabClick);
        }

        // Report type change
        var reportType = document.getElementById('reportType');
        if (reportType) {
            reportType.addEventListener('change', function() {
                loadReportData();
            });
        }

        // ESC key to close modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                closeExportModal();
            }
        });
    }
})();
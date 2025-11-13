// /admin/js/bookings-page-logic.js - Handles list page actions

(function(global) {
    'use strict';

    if (!global.AdminUtils) {
        console.error("AdminUtils (api-client.js) not loaded. Bookings Page actions disabled.");
        return;
    }
    
    // Import utilities and global state dependencies
    const ApiClient = global.AdminUtils.ApiClient;
    const { showSuccess, showError } = global.AdminUtils;
    // Assuming AdminUtils is loaded before this script and before booking-manager.js

    // --- Core Action Delegation ---
    document.addEventListener('click', (e) => {
        const target = e.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;
        const bookingId = target.dataset.bookingId;
        const newStatus = target.dataset.newStatus;
        
        if (!bookingId) return;

        switch (action) {
            case 'view-detail-modal':
                // Use the function exposed by booking-manager.js
                if (global.dashboardShowBookingDetail) {
                    global.dashboardShowBookingDetail(bookingId);
                } else {
                    showError('Modal component not loaded.');
                }
                break;
            case 'open-assign-modal':
                // The booking-manager's showAssignStaffModal needs the booking data first.
                // We use the same view logic as the dashboard calendar click: fetch, set state, open modal
                if (global.dashboardShowBookingDetail) {
                    // This function fetches the booking and opens the detail modal which contains the assign button
                    // which in turn opens the assignment modal.
                    global.dashboardShowBookingDetail(bookingId);
                }
                break;
            case 'update-status-api':
                if (newStatus) {
                    updateBookingStatusApi(bookingId, newStatus, target);
                }
                break;
            case 'send-client-reminder':
                sendClientReminderApi(bookingId, target);
                break;
        }
    });
    
    // --- API Interaction Functions ---

    // Function to update status (Mark Complete, Confirm, etc.)
    async function updateBookingStatusApi(bookingId, newStatusLabel, buttonElement) {
        if (!confirm(`Are you sure you want to change booking ${bookingId} status to ${newStatusLabel}?`)) {
            return;
        }
        
        const originalHtml = buttonElement.innerHTML;
        buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        buttonElement.disabled = true;
        
        try {
            // Call the PUT endpoint on /api/booking.php
            await ApiClient.put('api/booking.php', {
                booking_id: bookingId,
                status_label: newStatusLabel,
            });
            showSuccess(`Booking ${bookingId} status updated to ${newStatusLabel}!`);
            // Hard reload is often acceptable for a list page after a mutation.
            window.location.reload(); 
        } catch (error) {
            // Error handling is centralized in ApiClient
            buttonElement.innerHTML = originalHtml;
            buttonElement.disabled = false;
        }
    }
    
    // Function to manually trigger client reminder
    async function sendClientReminderApi(bookingId, buttonElement) {
        if (!confirm('Manually send client reminder SMS/Email now?')) {
            return;
        }
        
        const originalHtml = buttonElement.innerHTML;
        buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
        buttonElement.disabled = true;

        try {
            // NOTE: This assumes you create a simple POST API at /api/send-reminder.php
            await ApiClient.post('api/booking.php', { action: 'send_reminder', booking_id: bookingId });
            showSuccess('Reminder successfully dispatched!');
        } catch (error) {
            // Error handling is centralized in ApiClient
        } finally {
            buttonElement.innerHTML = originalHtml;
            buttonElement.disabled = false;
        }
    }

})(window);

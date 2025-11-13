/**
 * Business Settings Management JavaScript
 */

class SettingsManager {
    constructor() {
        const ApiClientRef = (window.AdminUtils && window.AdminUtils.ApiClient) || window.ApiClient;
        this.apiClient = typeof ApiClientRef === 'function' ? new ApiClientRef() : ApiClientRef;
        this.settings = {};
        this.endpoints = (window.ADMIN_CONFIG && window.ADMIN_CONFIG.endpoints) || {};
        this.init();
    }

    async init() {
        await this.loadSettings();
        this.bindEvents();
        this.renderBusinessHours();
    }

    async loadSettings() {
        try {
            const url = this.endpoints.settings || 'api/settings.php';
            const response = await this.apiClient.get(url);
            this.settings = response.settings || response.data || {};
            this.populateForm();
        } catch (error) {
            console.error('Failed to load settings:', error);
            showNotification('Failed to load settings', 'error');
        }
    }

    populateForm() {
        // Business Information
        if (this.settings.business_info) {
            document.getElementById('businessName').value = this.settings.business_info.name || '';
            document.getElementById('supportEmail').value = this.settings.business_info.email || '';
            document.getElementById('phoneNumber').value = this.settings.business_info.phone || '';
            document.getElementById('defaultDuration').value = this.settings.business_info.default_duration || 180;
        }

        // Notification Preferences
        if (this.settings.notifications) {
            const notifs = this.settings.notifications;
            document.getElementById('notifyEmailBookings').checked = notifs.email_bookings || false;
            document.getElementById('notifyEmailPayments').checked = notifs.email_payments || false;
            document.getElementById('notifyEmailStaff').checked = notifs.email_staff || false;
            document.getElementById('notifySMSBookings').checked = notifs.sms_bookings || false;
            document.getElementById('notifySMSReminders').checked = notifs.sms_reminders || false;
            document.getElementById('notifySMSUpdates').checked = notifs.sms_updates || false;
        }
    }

    renderBusinessHours() {
        const container = document.getElementById('businessHoursContainer');
        const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        const dayNames = {
            monday: 'Monday',
            tuesday: 'Tuesday',
            wednesday: 'Wednesday',
            thursday: 'Thursday',
            friday: 'Friday',
            saturday: 'Saturday',
            sunday: 'Sunday'
        };

        container.innerHTML = days.map(day => {
            const hours = this.settings.business_hours?.[day] || { open: '09:00', close: '17:00', closed: false };
            return `
                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-center space-x-4">
                        <div class="w-32">
                            <label class="form-label">${dayNames[day]}</label>
                        </div>
                        <div class="flex items-center space-x-2">
                            <input type="time" name="business_hours[${day}][open]" 
                                value="${hours.open}" 
                                ${hours.closed ? 'disabled' : ''}
                                class="border border-gray-300 rounded px-2 py-1">
                            <span>to</span>
                            <input type="time" name="business_hours[${day}][close]" 
                                value="${hours.close}" 
                                ${hours.closed ? 'disabled' : ''}
                                class="border border-gray-300 rounded px-2 py-1">
                        </div>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="closed${day}" 
                            name="business_hours[${day}][closed]" 
                            ${hours.closed ? 'checked' : ''}
                            onchange="settingsManager.toggleDayHours('${day}', this.checked)">
                        <label for="closed${day}" class="text-sm text-gray-700">Closed</label>
                    </div>
                </div>
            `;
        }).join('');
    }

    toggleDayHours(day, closed) {
        const inputs = document.querySelectorAll(`input[name="business_hours[${day}][open]"], input[name="business_hours[${day}][close]"]`);
        inputs.forEach(input => {
            input.disabled = closed;
        });
    }

    async saveSettings() {
        try {
            showGlobalLoader();

            // Build JSON payload expected by the API
            const payload = {
                business_name: document.getElementById('businessName').value,
                email: document.getElementById('supportEmail').value,
                phone: document.getElementById('phoneNumber').value,
                default_duration: parseInt(document.getElementById('defaultDuration').value || '180', 10),
                // Aggregate toggles so current API can store a compact preference
                notify_email: (
                    document.getElementById('notifyEmailBookings').checked ||
                    document.getElementById('notifyEmailPayments').checked ||
                    document.getElementById('notifyEmailStaff').checked
                ) ? 1 : 0,
                notify_sms: (
                    document.getElementById('notifySMSBookings').checked ||
                    document.getElementById('notifySMSReminders').checked ||
                    document.getElementById('notifySMSUpdates').checked
                ) ? 1 : 0,
            };

            const url = this.endpoints.settings || 'api/settings.php';
            await this.apiClient.post(url, payload);
            this.showSuccessMessage();
        } catch (error) {
            console.error('Failed to save settings:', error);
            showNotification('Failed to save settings', 'error');
        } finally {
            hideGlobalLoader();
        }
    }

    showSuccessMessage() {
        const message = document.getElementById('successMessage');
        message.style.display = 'block';
        setTimeout(() => {
            message.style.display = 'none';
        }, 3000);
    }

    bindEvents() {
        document.getElementById('saveSettingsBtn').addEventListener('click', () => this.saveSettings());
    }
}

// Utility Functions
function showGlobalLoader() {
    document.getElementById('globalLoader').style.display = 'flex';
}

function hideGlobalLoader() {
    document.getElementById('globalLoader').style.display = 'none';
}

function showNotification(message, type = 'info') {
    console.log(`${type.toUpperCase()}: ${message}`);
    alert(message);
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.settingsManager = new SettingsManager();
});

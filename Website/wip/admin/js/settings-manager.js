/**
 * Business Settings Management JavaScript
 */

class SettingsManager {
    constructor() {
        this.apiClient = new ApiClient();
        this.settings = {};
        this.init();
    }

    async init() {
        await this.loadSettings();
        this.bindEvents();
        this.renderBusinessHours();
    }

    async loadSettings() {
        try {
            const response = await this.apiClient.get('settings');
            this.settings = response.data || {};
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
            
            const formData = new FormData();
            
            // Business Information
            formData.append('business_name', document.getElementById('businessName').value);
            formData.append('email', document.getElementById('supportEmail').value);
            formData.append('phone', document.getElementById('phoneNumber').value);
            formData.append('default_duration', document.getElementById('defaultDuration').value);

            // Notification Preferences
            formData.append('notify_email_bookings', document.getElementById('notifyEmailBookings').checked ? '1' : '0');
            formData.append('notify_email_payments', document.getElementById('notifyEmailPayments').checked ? '1' : '0');
            formData.append('notify_email_staff', document.getElementById('notifyEmailStaff').checked ? '1' : '0');
            formData.append('notify_sms_bookings', document.getElementById('notifySMSBookings').checked ? '1' : '0');
            formData.append('notify_sms_reminders', document.getElementById('notifySMSReminders').checked ? '1' : '0');
            formData.append('notify_sms_updates', document.getElementById('notifySMSUpdates').checked ? '1' : '0');

            // Business Hours
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            days.forEach(day => {
                const openInput = document.querySelector(`input[name="business_hours[${day}][open]"]`);
                const closeInput = document.querySelector(`input[name="business_hours[${day}][close]"]`);
                const closedInput = document.querySelector(`input[name="business_hours[${day}][closed]"]`);
                
                if (openInput && closeInput && closedInput) {
                    formData.append(`business_hours[${day}][open]`, openInput.value);
                    formData.append(`business_hours[${day}][close]`, closeInput.value);
                    formData.append(`business_hours[${day}][closed]`, closedInput.checked ? '1' : '0');
                }
            });

            await this.apiClient.post('settings', formData);
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
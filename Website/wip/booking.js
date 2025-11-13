'use strict';

(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const config = window.BOOKING_CONFIG || {};
        const steps = Array.from(document.querySelectorAll('[data-step]'));
        const totalSteps = steps.length;

        const progressBar = document.getElementById('progressBar');
        const indicatorCircles = Array.from(document.querySelectorAll('.step-circle'));
        const serviceButtons = Array.from(document.querySelectorAll('[data-service]'));
        const frequencyButtons = Array.from(document.querySelectorAll('[data-frequency]'));
        const fieldElements = Array.from(document.querySelectorAll('[data-field]'));

        const backButton = document.getElementById('backButton');
        const nextButton = document.getElementById('nextButton');
        const confirmButton = document.getElementById('confirmButton');

        const errorBox = document.getElementById('formError');
        const successBox = document.getElementById('successMessage');

        const estimatePreview = document.getElementById('estimatePreview');
        const estimatePreviewValue = document.getElementById('estimatePreviewValue');

        const summaryFields = {
            service: document.getElementById('summaryService'),
            property: document.getElementById('summaryProperty'),
            rooms: document.getElementById('summaryRooms'),
            schedule: document.getElementById('summarySchedule'),
            contact: document.getElementById('summaryContact'),
            email: document.getElementById('summaryEmail'),
            phone: document.getElementById('summaryPhone'),
            address: document.getElementById('summaryAddress'),
            notes: document.getElementById('summaryNotes'),
            price: document.getElementById('summaryPrice'),
        };

        const supportPhone = config.supportPhone || '(385) 213-8900';

        const serviceCatalog = {
            regular: { name: 'Standard Cleaning', basePrice: 129 },
            deep: { name: 'Deep Cleaning', basePrice: 249 },
            move: { name: 'Move In / Move Out', basePrice: 299 },
            onetime: { name: 'One-Time Cleaning', basePrice: 159 },
        };

        const frequencyCatalog = {
            weekly: { name: 'Weekly', discount: 0.15 },
            biweekly: { name: 'Bi-Weekly', discount: 0.10 },
            monthly: { name: 'Monthly', discount: 0.05 },
            onetime: { name: 'One-Time', discount: 0 },
        };

        const propertyLabels = {
            house: 'House',
            apartment: 'Apartment',
            condo: 'Condo',
            townhouse: 'Townhouse',
        };

        const defaultData = {
            serviceType: '',
            frequency: '',
            propertyType: '',
            squareFeet: '',
            bedrooms: '',
            bathrooms: '',
            date: '',
            time: '',
            firstName: '',
            lastName: '',
            email: '',
            phone: '',
            address: '',
            city: '',
            state: 'UT',
            zip: '',
            notes: '',
        };

        let currentStep = 1;
        const state = {
            data: { ...defaultData },
        };

        loadDraftIfAvailable();
        attachEventListeners();
        updateEstimateUI();
        refreshUI();

        function attachEventListeners() {
            serviceButtons.forEach(button => {
                button.addEventListener('click', () => {
                    selectService(button.dataset.service);
                });
            });

            frequencyButtons.forEach(button => {
                button.addEventListener('click', () => {
                    selectFrequency(button.dataset.frequency);
                });
            });

            fieldElements.forEach(element => {
                const handler = element.tagName === 'SELECT' || element.type === 'date' ? 'change' : 'input';
                element.addEventListener(handler, event => {
                    handleFieldUpdate(event.target);
                });
            });

            nextButton.addEventListener('click', () => {
                if (!validateStep(currentStep)) {
                    return;
                }
                showStep(currentStep + 1);
            });

            backButton.addEventListener('click', () => {
                if (currentStep > 1) {
                    showStep(currentStep - 1);
                }
            });

            confirmButton.addEventListener('click', () => {
                if (!validateStep(totalSteps)) {
                    return;
                }
                submitBooking();
            });
        }

        function selectService(serviceId) {
            state.data.serviceType = serviceId;
            serviceButtons.forEach(button => {
                button.classList.toggle('active', button.dataset.service === serviceId);
            });
            clearMessages();
            updateEstimateUI();
            saveDraft();
            refreshNavigationState();
        }

        function selectFrequency(frequencyId) {
            state.data.frequency = frequencyId;
            frequencyButtons.forEach(button => {
                button.classList.toggle('active', button.dataset.frequency === frequencyId);
            });
            clearMessages();
            updateEstimateUI();
            saveDraft();
            refreshNavigationState();
        }

        function handleFieldUpdate(element) {
            const field = element.dataset.field;
            if (!field) {
                return;
            }

            let value = element.value.trim();

            if (field === 'phone') {
                value = formatPhone(value);
                element.value = value;
            }

            if (field === 'squareFeet' && value !== '') {
                value = String(Math.max(0, parseInt(value, 10) || 0));
                element.value = value;
            }

            state.data[field] = value;
            updateEstimateUI();
            saveDraft();
            refreshNavigationState();
        }

        function refreshUI() {
            showStep(currentStep);
            applyStateToInputs();
            updateEstimateUI();
            refreshNavigationState();
        }

        function showStep(step) {
            currentStep = clamp(step, 1, totalSteps);
            steps.forEach(section => {
                const panelStep = parseInt(section.dataset.step, 10);
                section.classList.toggle('hidden', panelStep !== currentStep);
            });
            updateProgressBar();
            updateStepIndicators();
            refreshNavigationState();
            clearMessages();

            if (currentStep === totalSteps) {
                renderSummary();
            }

            saveDraft();
        }

        function refreshNavigationState() {
            backButton.classList.toggle('hidden', currentStep === 1);
            nextButton.classList.toggle('hidden', currentStep === totalSteps);
            confirmButton.classList.toggle('hidden', currentStep !== totalSteps);

            if (!nextButton.classList.contains('hidden')) {
                nextButton.disabled = !canProceed(currentStep);
                nextButton.textContent = currentStep === totalSteps - 1 ? 'Review Booking' : 'Continue';
            }

            if (!confirmButton.classList.contains('hidden')) {
                confirmButton.disabled = !canProceed(totalSteps);
            }
        }

        function updateProgressBar() {
            if (!progressBar) {
                return;
            }
            const progress = totalSteps > 1 ? ((currentStep - 1) / (totalSteps - 1)) * 100 : 100;
            progressBar.style.width = `${progress}%`;
        }

        function updateStepIndicators() {
            indicatorCircles.forEach(circle => {
                const stepNumber = parseInt(circle.dataset.indicator, 10);
                const wrapper = circle.parentElement;
                if (!stepNumber || !wrapper) {
                    return;
                }

                wrapper.classList.remove('step-active', 'step-complete');

                if (stepNumber < currentStep) {
                    wrapper.classList.add('step-complete');
                    circle.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>';
                } else if (stepNumber === currentStep) {
                    wrapper.classList.add('step-active');
                    circle.textContent = String(stepNumber);
                } else {
                    circle.textContent = String(stepNumber);
                }
            });
        }

        function updateEstimateUI() {
            const estimate = calculateEstimate(state.data);

            if (estimatePreview && estimatePreviewValue) {
                if (estimate > 0) {
                    estimatePreview.classList.remove('hidden');
                    estimatePreviewValue.textContent = formatCurrency(estimate);
                } else {
                    estimatePreview.classList.add('hidden');
                }
            }

            if (summaryFields.price && currentStep === totalSteps) {
                summaryFields.price.textContent = formatCurrency(estimate);
            }
        }

        function calculateEstimate(data) {
            const service = serviceCatalog[data.serviceType];
            if (!service) {
                return 0;
            }

            const bedrooms = data.bedrooms;
            const bedroomMultiplier = {
                '1': 1,
                '2': 1.3,
                '3': 1.6,
                '4': 2.0,
                '5': 2.5,
            };

            const multiplier = bedroomMultiplier[bedrooms] || 1;
            let estimate = service.basePrice * multiplier;

            const frequency = frequencyCatalog[data.frequency];
            if (frequency) {
                estimate *= (1 - frequency.discount);
            }

            return Math.round(estimate * 100) / 100;
        }

        function canProceed(step) {
            const data = state.data;
            switch (step) {
                case 1:
                    return Boolean(data.serviceType);
                case 2:
                    return Boolean(data.frequency && data.propertyType && data.bedrooms && data.bathrooms);
                case 3:
                    return Boolean(data.date && data.time);
                case 4:
                case 5:
                    return Boolean(
                        data.firstName &&
                        data.lastName &&
                        data.email &&
                        validateEmail(data.email) &&
                        validatePhone(data.phone) &&
                        data.address &&
                        data.city &&
                        data.state &&
                        validateZip(data.zip)
                    );
                default:
                    return true;
            }
        }

        function validateStep(step) {
            const data = state.data;
            let message = '';

            switch (step) {
                case 1:
                    if (!data.serviceType) {
                        message = 'Please select the type of cleaning you need.';
                    }
                    break;
                case 2:
                    if (!data.frequency) {
                        message = 'Select how often you would like us to clean.';
                    } else if (!data.propertyType) {
                        message = 'Choose a property type so we can tailor the service.';
                    } else if (!data.bedrooms || !data.bathrooms) {
                        message = 'Let us know how many bedrooms and bathrooms you have.';
                    }
                    break;
                case 3:
                    if (!data.date) {
                        message = 'Please pick the date for your cleaning.';
                    } else if (!isFutureDate(data.date)) {
                        message = 'Selected date cannot be in the past.';
                    } else if (!data.time) {
                        message = 'Select an arrival window for our team.';
                    }
                    break;
                case 4:
                case 5:
                    if (!data.firstName || !data.lastName) {
                        message = 'Please provide the primary contact name.';
                    } else if (!data.email || !validateEmail(data.email)) {
                        message = 'Enter a valid email address so we can send booking updates.';
                    } else if (!validatePhone(data.phone)) {
                        message = 'Enter a 10-digit phone number (numbers only).';
                    } else if (!data.address || !data.city || !data.state) {
                        message = 'Provide the full service address, including city and state.';
                    } else if (!validateZip(data.zip)) {
                        message = 'Enter a valid ZIP code (e.g. 84101 or 84101-1234).';
                    } else if (!data.date || !data.time) {
                        message = 'Please confirm the schedule for your cleaning.';
                    } else if (!data.serviceType || !data.frequency) {
                        message = 'Select a service and frequency before confirming.';
                    }
                    break;
                default:
                    break;
            }

            if (message) {
                displayError(message);
                return false;
            }

            clearMessages();
            return true;
        }

        function renderSummary() {
            const data = state.data;
            const service = serviceCatalog[data.serviceType];
            const frequency = frequencyCatalog[data.frequency];
            const estimate = calculateEstimate(data);

            summaryFields.service.textContent = service && frequency
                ? `${service.name} — ${frequency.name}`
                : 'Not specified';

            summaryFields.property.textContent = propertyLabels[data.propertyType] || 'Not specified';
            summaryFields.rooms.textContent = data.bedrooms && data.bathrooms
                ? `${data.bedrooms} BR / ${data.bathrooms} BA`
                : 'Not specified';

            if (data.date && data.time) {
                summaryFields.schedule.textContent = `${formatDate(data.date)} at ${data.time}`;
            } else {
                summaryFields.schedule.textContent = 'Not scheduled yet';
            }

            summaryFields.contact.textContent = data.firstName && data.lastName
                ? `${data.firstName} ${data.lastName}`
                : 'Not provided';

            summaryFields.email.textContent = data.email || 'Not provided';
            summaryFields.phone.textContent = data.phone || 'Not provided';

            summaryFields.address.textContent = data.address
                ? `${data.address}, ${data.city || ''}, ${data.state || ''} ${data.zip || ''}`.replace(/\s+/g, ' ').trim()
                : 'Not provided';

            summaryFields.notes.textContent = data.notes
                ? data.notes
                : 'No special instructions provided.';

            summaryFields.price.textContent = formatCurrency(estimate);
        }

        async function submitBooking() {
            confirmButton.disabled = true;
            confirmButton.textContent = 'Submitting...';
            clearMessages();

            try {
                const payload = {
                    ...state.data,
                    csrf_token: config.csrfToken,
                };

                const response = await fetch('submit-booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    throw new Error('We could not submit your booking. Please try again in a moment.');
                }

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.message || 'We could not submit your booking. Please try again.');
                }

                sessionStorage.removeItem('booking_wizard');
                renderSummary();
                displaySuccess(result.message || 'Booking confirmed! We will reach out soon.');

                if (result.bookingId) {
                    successBox.innerHTML = `<strong>Success!</strong> Booking reference: <span class="font-mono text-emerald-700">${result.bookingId}</span>. ${result.message || ''}`;
                }

                confirmButton.textContent = 'Booking Confirmed';
                confirmButton.classList.add('opacity-75', 'cursor-not-allowed');
                backButton.classList.add('hidden');
                nextButton.classList.add('hidden');
            } catch (error) {
                displayError(error.message || `Something went wrong. Contact us at ${supportPhone}.`);
                confirmButton.disabled = false;
                confirmButton.textContent = 'Confirm Booking';
            }
        }

        function loadDraftIfAvailable() {
            try {
                const raw = sessionStorage.getItem('booking_wizard');
                if (!raw) {
                    showStep(1);
                    return;
                }

                const parsed = JSON.parse(raw);
                if (!parsed || !parsed.data) {
                    showStep(1);
                    return;
                }

                const age = Date.now() - (parsed.timestamp || 0);
                const oneHour = 1000 * 60 * 60;

                if (age > oneHour) {
                    sessionStorage.removeItem('booking_wizard');
                    showStep(1);
                    return;
                }

                if (window.confirm('We found a saved booking in progress. Would you like to continue?')) {
                    state.data = { ...defaultData, ...parsed.data };
                    currentStep = clamp(parsed.step || 1, 1, totalSteps);
                    applyStateToInputs();
                    showStep(currentStep);
                    updateEstimateUI();
                } else {
                    sessionStorage.removeItem('booking_wizard');
                    showStep(1);
                }
            } catch (error) {
                console.warn('Failed to restore saved data:', error);
                showStep(1);
            }
        }

        function saveDraft() {
            try {
                sessionStorage.setItem('booking_wizard', JSON.stringify({
                    data: state.data,
                    step: currentStep,
                    timestamp: Date.now(),
                }));
            } catch (error) {
                console.warn('Failed to save draft booking:', error);
            }
        }

        function applyStateToInputs() {
            const data = state.data;

            serviceButtons.forEach(button => {
                button.classList.toggle('active', button.dataset.service === data.serviceType);
            });

            frequencyButtons.forEach(button => {
                button.classList.toggle('active', button.dataset.frequency === data.frequency);
            });

            fieldElements.forEach(element => {
                const field = element.dataset.field;
                if (!field) {
                    return;
                }

                const value = data[field] ?? '';

                if (element.type === 'checkbox') {
                    element.checked = Boolean(value);
                } else {
                    element.value = value;
                }
            });
        }

        function displayError(message) {
            if (!errorBox) {
                alert(message);
                return;
            }
            errorBox.textContent = message;
            errorBox.classList.remove('hidden');
        }

        function displaySuccess(message) {
            if (!successBox) {
                alert(message);
                return;
            }
            successBox.textContent = message;
            successBox.classList.remove('hidden');
        }

        function clearMessages() {
            if (errorBox) {
                errorBox.classList.add('hidden');
                errorBox.textContent = '';
            }

            if (successBox) {
                successBox.classList.add('hidden');
                successBox.textContent = '';
            }
        }

        function formatPhone(value) {
            const digits = value.replace(/\D/g, '').slice(0, 10);

            if (digits.length < 4) {
                return digits;
            }

            if (digits.length < 7) {
                return `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
            }

            return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
        }

        function validateEmail(value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value || '');
        }

        function validatePhone(value) {
            const digits = (value || '').replace(/\D/g, '');
            return digits.length === 10;
        }

        function validateZip(value) {
            return /^\d{5}(-\d{4})?$/.test(value || '');
        }

        function isFutureDate(value) {
            if (!value) {
                return false;
            }
            const selected = new Date(value);
            const today = new Date();
            selected.setHours(0, 0, 0, 0);
            today.setHours(0, 0, 0, 0);
            return selected >= today;
        }

        function formatDate(value) {
            if (!value) {
                return '';
            }
            try {
                const date = new Date(value);
                return new Intl.DateTimeFormat('en-US', {
                    weekday: 'long',
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                }).format(date);
            } catch (_) {
                return value;
            }
        }

        function formatCurrency(amount) {
            if (!Number.isFinite(amount)) {
                return '$0.00';
            }
            return `$${amount.toFixed(2)}`;
        }

        function clamp(value, min, max) {
            return Math.min(Math.max(value, min), max);
        }
    });
})();

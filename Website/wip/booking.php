<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book Your Cleaning - Wasatch Cleaners</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php
    require_once __DIR__ . '/app/bootstrap.php';
    $csrfToken = generateCSRFToken();
    ?>
</head>
<body class="min-h-screen bg-gray-50">
    <header class="bg-white shadow-sm sticky top-0 z-40">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <img src="logo.png" alt="Wasatch Cleaners logo" class="h-10 w-10 mr-3 object-contain" />
                    <span class="text-2xl font-bold text-gray-900 tracking-tight">Wasatch Cleaners</span>
                </div>
                <div class="text-sm text-gray-600">
                    Need help? <span class="text-teal-600 font-semibold">(385) 213-8900</span>
                </div>
            </div>
        </div>
    </header>

    <main class="py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-xl rounded-3xl overflow-hidden">
                <div class="px-6 py-10 md:px-12">
                    <div class="mb-10">
                        <div class="relative pt-10">
                            <div class="absolute inset-x-0 top-5 h-1 bg-gray-200 rounded-full">
                                <div id="progressBar" class="h-1 bg-teal-500 rounded-full transition-all duration-500" style="width: 0%;"></div>
                            </div>
                            <div class="flex justify-between relative z-10">
                                <div class="flex flex-col items-center">
                                    <div class="step-circle" data-indicator="1">1</div>
                                    <span class="step-label">Service</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="step-circle" data-indicator="2">2</div>
                                    <span class="step-label">Details</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="step-circle" data-indicator="3">3</div>
                                    <span class="step-label">Schedule</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="step-circle" data-indicator="4">4</div>
                                    <span class="step-label">Contact</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="step-circle" data-indicator="5">5</div>
                                    <span class="step-label">Review</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="formError" class="hidden mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></div>
                    <div id="successMessage" class="hidden mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"></div>

                    <div id="stepsContainer" class="space-y-8">
                        <!-- Step 1: Service Selection -->
                        <section data-step="1" class="step-panel">
                            <div class="text-center mb-8">
                                <p class="text-teal-600 font-semibold uppercase tracking-wide text-sm mb-2">Step 1</p>
                                <h2 class="text-3xl font-bold text-gray-900">What type of cleaning do you need?</h2>
                                <p class="text-gray-600 mt-3">Select the service that best fits your home and schedule.</p>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2" id="serviceOptions">
                                <button type="button" class="service-card" data-service="regular">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="text-xl font-semibold text-gray-900">Standard Cleaning</h3>
                                        <span class="text-teal-600 font-semibold">$129</span>
                                    </div>
                                    <p class="text-sm text-gray-600">Perfect for routine upkeep on a weekly or bi-weekly cadence.</p>
                                </button>
                                <button type="button" class="service-card" data-service="deep">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="text-xl font-semibold text-gray-900">Deep Cleaning</h3>
                                        <span class="text-teal-600 font-semibold">$249</span>
                                    </div>
                                    <p class="text-sm text-gray-600">Detailed top-to-bottom cleaning ideal for spring refreshes.</p>
                                </button>
                                <button type="button" class="service-card" data-service="move">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="text-xl font-semibold text-gray-900">Move In / Move Out</h3>
                                        <span class="text-teal-600 font-semibold">$299</span>
                                    </div>
                                    <p class="text-sm text-gray-600">Comprehensive cleaning designed for moving day transitions.</p>
                                </button>
                                <button type="button" class="service-card" data-service="onetime">
                                    <div class="flex justify-between items-start mb-2">
                                        <h3 class="text-xl font-semibold text-gray-900">One-Time Cleaning</h3>
                                        <span class="text-teal-600 font-semibold">$159</span>
                                    </div>
                                    <p class="text-sm text-gray-600">A single appointment to get your space guest-ready and sparkling.</p>
                                </button>
                            </div>
                        </section>

                        <!-- Step 2: Property Details -->
                        <section data-step="2" class="step-panel hidden">
                            <div class="text-center mb-8">
                                <p class="text-teal-600 font-semibold uppercase tracking-wide text-sm mb-2">Step 2</p>
                                <h2 class="text-3xl font-bold text-gray-900">Tell us about your home</h2>
                                <p class="text-gray-600 mt-3">We’ll tailor the service and estimate based on your selections.</p>
                            </div>
                            <div class="space-y-8">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Cleaning Frequency</h3>
                                    <div class="grid gap-3 sm:grid-cols-4">
                                        <button type="button" class="frequency-card" data-frequency="weekly">
                                            <span class="font-semibold text-gray-900 block">Weekly</span>
                                            <span class="text-teal-600 text-xs font-medium uppercase">Save 15%</span>
                                        </button>
                                        <button type="button" class="frequency-card" data-frequency="biweekly">
                                            <span class="font-semibold text-gray-900 block">Bi-Weekly</span>
                                            <span class="text-teal-600 text-xs font-medium uppercase">Save 10%</span>
                                        </button>
                                        <button type="button" class="frequency-card" data-frequency="monthly">
                                            <span class="font-semibold text-gray-900 block">Monthly</span>
                                            <span class="text-teal-600 text-xs font-medium uppercase">Save 5%</span>
                                        </button>
                                        <button type="button" class="frequency-card" data-frequency="onetime">
                                            <span class="font-semibold text-gray-900 block">One-Time</span>
                                            <span class="text-gray-500 text-xs font-medium uppercase">Standard Rate</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="grid gap-6 md:grid-cols-2">
                                    <div>
                                        <label for="propertyType" class="block text-sm font-semibold text-gray-700 mb-2">Property Type</label>
                                        <select id="propertyType" class="form-input" data-field="propertyType">
                                            <option value="">Select property type</option>
                                            <option value="house">House</option>
                                            <option value="apartment">Apartment</option>
                                            <option value="condo">Condo</option>
                                            <option value="townhouse">Townhouse</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="squareFeet" class="block text-sm font-semibold text-gray-700 mb-2">Square Footage (optional)</label>
                                        <input type="number" id="squareFeet" class="form-input" placeholder="Approximate size" data-field="squareFeet" min="0" step="50">
                                    </div>
                                </div>

                                <div class="grid gap-6 md:grid-cols-2">
                                    <div>
                                        <label for="bedrooms" class="block text-sm font-semibold text-gray-700 mb-2">Bedrooms</label>
                                        <select id="bedrooms" class="form-input" data-field="bedrooms">
                                            <option value="">Select bedrooms</option>
                                            <option value="1">1 Bedroom</option>
                                            <option value="2">2 Bedrooms</option>
                                            <option value="3">3 Bedrooms</option>
                                            <option value="4">4 Bedrooms</option>
                                            <option value="5">5+ Bedrooms</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="bathrooms" class="block text-sm font-semibold text-gray-700 mb-2">Bathrooms</label>
                                        <select id="bathrooms" class="form-input" data-field="bathrooms">
                                            <option value="">Select bathrooms</option>
                                            <option value="1">1 Bathroom</option>
                                            <option value="2">2 Bathrooms</option>
                                            <option value="3">3 Bathrooms</option>
                                            <option value="4">4+ Bathrooms</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label for="notes" class="block text-sm font-semibold text-gray-700 mb-2">Special Instructions (optional)</label>
                                    <textarea id="notes" class="form-input resize-none h-32" placeholder="Tell us about pets, access instructions, or priority areas." data-field="notes"></textarea>
                                </div>

                                <div id="estimatePreview" class="hidden rounded-2xl border border-teal-200 bg-teal-50 px-6 py-5">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-sm font-semibold uppercase text-teal-700 tracking-wide">Estimated Total</p>
                                            <p class="text-sm text-teal-700">Final price confirmed after the first walkthrough.</p>
                                        </div>
                                        <div class="mt-4 sm:mt-0 text-4xl font-extrabold text-teal-600" id="estimatePreviewValue">$0.00</div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Step 3: Schedule -->
                        <section data-step="3" class="step-panel hidden">
                            <div class="text-center mb-8">
                                <p class="text-teal-600 font-semibold uppercase tracking-wide text-sm mb-2">Step 3</p>
                                <h2 class="text-3xl font-bold text-gray-900">Schedule your clean</h2>
                                <p class="text-gray-600 mt-3">Choose a date and arrival window that works for you.</p>
                            </div>
                            <div class="grid gap-6 md:grid-cols-2">
                                <div>
                                    <label for="appointmentDate" class="block text-sm font-semibold text-gray-700 mb-2">Preferred Date</label>
                                    <input type="date" id="appointmentDate" class="form-input" data-field="date" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div>
                                    <label for="appointmentTime" class="block text-sm font-semibold text-gray-700 mb-2">Arrival Window</label>
                                    <select id="appointmentTime" class="form-input" data-field="time">
                                        <option value="">Select a time</option>
                                        <option value="08:00 AM">8:00 AM</option>
                                        <option value="09:00 AM">9:00 AM</option>
                                        <option value="10:00 AM">10:00 AM</option>
                                        <option value="11:00 AM">11:00 AM</option>
                                        <option value="12:00 PM">12:00 PM</option>
                                        <option value="01:00 PM">1:00 PM</option>
                                        <option value="02:00 PM">2:00 PM</option>
                                        <option value="03:00 PM">3:00 PM</option>
                                        <option value="04:00 PM">4:00 PM</option>
                                    </select>
                                </div>
                            </div>
                        </section>

                        <!-- Step 4: Contact -->
                        <section data-step="4" class="step-panel hidden">
                            <div class="text-center mb-8">
                                <p class="text-teal-600 font-semibold uppercase tracking-wide text-sm mb-2">Step 4</p>
                                <h2 class="text-3xl font-bold text-gray-900">How can we reach you?</h2>
                                <p class="text-gray-600 mt-3">We’ll confirm all details and send reminders before we arrive.</p>
                            </div>

                            <div class="grid gap-6 md:grid-cols-2">
                                <div>
                                    <label for="firstName" class="block text-sm font-semibold text-gray-700 mb-2">First Name</label>
                                    <input type="text" id="firstName" class="form-input" data-field="firstName" placeholder="Jane">
                                </div>
                                <div>
                                    <label for="lastName" class="block text-sm font-semibold text-gray-700 mb-2">Last Name</label>
                                    <input type="text" id="lastName" class="form-input" data-field="lastName" placeholder="Doe">
                                </div>
                            </div>

                            <div class="grid gap-6 md:grid-cols-2 mt-6">
                                <div>
                                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                    <input type="email" id="email" class="form-input" data-field="email" placeholder="you@email.com">
                                </div>
                                <div>
                                    <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Mobile Phone</label>
                                    <input type="tel" id="phone" class="form-input" data-field="phone" placeholder="(385) 213-8900">
                                </div>
                            </div>

                            <div class="mt-6">
                                <label for="address" class="block text-sm font-semibold text-gray-700 mb-2">Street Address</label>
                                <input type="text" id="address" class="form-input" data-field="address" placeholder="123 Main Street">
                            </div>

                            <div class="grid gap-6 md:grid-cols-3 mt-6">
                                <div class="md:col-span-2">
                                    <label for="city" class="block text-sm font-semibold text-gray-700 mb-2">City</label>
                                    <input type="text" id="city" class="form-input" data-field="city" placeholder="Salt Lake City">
                                </div>
                                <div>
                                    <label for="state" class="block text-sm font-semibold text-gray-700 mb-2">State</label>
                                    <select id="state" class="form-input" data-field="state">
                                        <option value="UT">Utah</option>
                                        <option value="ID">Idaho</option>
                                        <option value="NV">Nevada</option>
                                        <option value="CO">Colorado</option>
                                        <option value="AZ">Arizona</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-6 md:w-1/3">
                                <label for="zip" class="block text-sm font-semibold text-gray-700 mb-2">ZIP Code</label>
                                <input type="text" id="zip" class="form-input" data-field="zip" placeholder="84101" maxlength="10">
                            </div>
                        </section>

                        <!-- Step 5: Review -->
                        <section data-step="5" class="step-panel hidden">
                            <div class="text-center mb-8">
                                <p class="text-teal-600 font-semibold uppercase tracking-wide text-sm mb-2">Step 5</p>
                                <h2 class="text-3xl font-bold text-gray-900">Review and confirm</h2>
                                <p class="text-gray-600 mt-3">Double-check everything below, then submit your booking.</p>
                            </div>

                            <div class="grid gap-6 lg:grid-cols-2">
                                <div class="rounded-2xl border border-gray-200 p-6">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Service Details</h3>
                                    <dl class="space-y-3 text-sm text-gray-700">
                                        <div class="flex justify-between">
                                            <dt class="font-medium text-gray-600">Service</dt>
                                            <dd id="summaryService" class="text-right"></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="font-medium text-gray-600">Property</dt>
                                            <dd id="summaryProperty" class="text-right"></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="font-medium text-gray-600">Bedrooms / Bathrooms</dt>
                                            <dd id="summaryRooms" class="text-right"></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="font-medium text-gray-600">Date &amp; Time</dt>
                                            <dd id="summarySchedule" class="text-right"></dd>
                                        </div>
                                    </dl>
                                </div>

                                <div class="rounded-2xl border border-gray-200 p-6">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Contact &amp; Address</h3>
                                    <dl class="space-y-3 text-sm text-gray-700">
                                        <div class="flex justify-between">
                                            <dt class="font-medium text-gray-600">Primary Contact</dt>
                                            <dd id="summaryContact" class="text-right"></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="font-medium text-gray-600">Email</dt>
                                            <dd id="summaryEmail" class="text-right"></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="font-medium text-gray-600">Phone</dt>
                                            <dd id="summaryPhone" class="text-right"></dd>
                                        </div>
                                        <div>
                                            <dt class="font-medium text-gray-600 mb-1">Service Address</dt>
                                            <dd id="summaryAddress" class="text-sm text-gray-700"></dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <div class="mt-6 rounded-2xl border border-teal-200 bg-teal-50 p-6">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div>
                                        <p class="text-sm uppercase tracking-wide font-semibold text-teal-700">Estimated Total</p>
                                        <p class="text-sm text-teal-700">Payment happens after the clean is completed.</p>
                                    </div>
                                    <div class="text-4xl font-extrabold text-teal-600" id="summaryPrice">$0.00</div>
                                </div>
                                <div class="mt-4 text-sm text-gray-600">
                                    <p id="summaryNotes" class="whitespace-pre-line"></p>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="mt-12 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4" id="navigationContainer">
                        <button type="button" id="backButton" class="nav-button secondary hidden">Back</button>
                        <div class="flex-1"></div>
                        <button type="button" id="nextButton" class="nav-button primary">Continue</button>
                        <button type="button" id="confirmButton" class="nav-button primary hidden">Confirm Booking</button>
                    </div>
                </div>
            </div>

            <section class="mt-12 bg-white rounded-3xl shadow-md p-8 grid gap-6 md:grid-cols-3">
                <div class="flex items-start gap-3">
                    <div class="text-teal-500">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M9 12l2 2 4-4"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Satisfaction Guaranteed</h3>
                        <p class="text-sm text-gray-600">If anything is missed, we’ll come back within 24 hours to make it right.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="text-teal-500">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Bonded &amp; Insured</h3>
                        <p class="text-sm text-gray-600">Every cleaner is background checked, trained, and fully insured.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="text-teal-500">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 8v4l3 3"></path>
                            <circle cx="12" cy="12" r="10"></circle>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">Automated Reminders</h3>
                        <p class="text-sm text-gray-600">We’ll text you the day before and when our team is en route.</p>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script>
        window.BOOKING_CONFIG = <?php echo json_encode([
            'csrfToken' => $csrfToken,
            'supportPhone' => '(385) 213-8900'
        ], JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="booking.js" defer></script>

    <style>
        .step-circle {
            width: 3rem;
            height: 3rem;
            border-radius: 9999px;
            border: 2px solid #e5e7eb;
            background-color: #ffffff;
            color: #6b7280;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .step-label {
            margin-top: 0.5rem;
            font-size: 0.625rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
            color: #9ca3af;
        }

        .step-active .step-circle {
            border-color: #0d9488;
            background-color: #0d9488;
            color: #ffffff;
            box-shadow: 0 12px 20px -10px rgba(13, 148, 136, 0.6);
        }

        .step-complete .step-circle {
            border-color: #0d9488;
            background-color: #ccfbf1;
            color: #115e59;
        }

        .step-complete .step-label,
        .step-active .step-label {
            color: #0f766e;
        }

        .service-card,
        .frequency-card {
            border-radius: 1.5rem;
            border: 1px solid #e5e7eb;
            background-color: #ffffff;
            padding: 1.5rem;
            text-align: left;
            transition: all 0.2s ease;
            box-shadow: none;
        }

        .service-card:hover,
        .frequency-card:hover,
        .service-card:focus,
        .frequency-card:focus {
            border-color: #5eead4;
            box-shadow: 0 20px 25px -20px rgba(13, 148, 136, 0.35);
            outline: none;
        }

        .service-card.active,
        .frequency-card.active {
            border-color: #0d9488;
            background-color: #f0fdfa;
            box-shadow: 0 25px 35px -25px rgba(13, 148, 136, 0.55);
        }

        .form-input {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            background-color: #ffffff;
            padding: 0.85rem 1rem;
            color: #1f2937;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(45, 212, 191, 0.25);
        }

        .nav-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            padding: 0.85rem 1.75rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .nav-button.primary {
            background-color: #f43f5e;
            color: #ffffff;
            box-shadow: 0 20px 25px -20px rgba(244, 63, 94, 0.65);
        }

        .nav-button.primary:hover {
            background-color: #e11d48;
        }

        .nav-button.primary:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.35);
        }

        .nav-button.secondary {
            background-color: #ffffff;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .nav-button.secondary:hover {
            background-color: #f9fafb;
        }

        .nav-button.secondary:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(209, 213, 219, 0.35);
        }
    </style>
</body>
</html>

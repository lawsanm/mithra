/* Fill the onboarding form for a sponsor walkthrough; saving stays explicit. */
(function () {
    'use strict';

    const button = document.querySelector('[data-fill-sponsor-demo]');
    if (!button) return;

    const form = button.form;
    const status = form.querySelector('[data-sponsor-demo-status]');
    const demo = {
        company_name: 'Lanka Green Solutions (Pvt) Ltd',
        contact_person: 'N. Perera',
        contact_email: 'n.perera@lankagreen.lk',
        contact_phone: '+94 11 245 6789',
        agreement_status: 'signed',
        agreement_details: 'CSR Partnership Agreement',
        internal_notes: 'Sponsor supports community lending and disaster-relief activities.',
        login_nic: '199512345678',
        login_phone: '077 456 7890',
        login_address: '125, Galle Road, Colombo 03, Sri Lanka',
        password: '12345678',
        password_confirmation: '12345678'
    };

    button.hidden = false;
    button.addEventListener('click', function () {
        Object.keys(demo).forEach(function (name) {
            const field = form.elements.namedItem(name);
            if (!field) return;
            field.value = demo[name];
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
        status.textContent = 'Demo data filled. Starting password: 12345678. Review the details, then select Connect sponsor to save.';
    });
}());

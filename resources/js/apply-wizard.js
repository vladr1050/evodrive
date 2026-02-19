document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('apply-form');
    if (!form) return;

    const steps = Array.from(document.querySelectorAll('[data-step]'));
    const progressBar = document.getElementById('progress-bar');
    const stepLabel = document.getElementById('step-label');
    const stepInput = document.getElementById('step-input');
    const totalSteps = 5;
    let currentStep = 1;

    function showStep(n) {
        currentStep = Math.max(1, Math.min(totalSteps, n));
        if (stepInput) stepInput.value = currentStep;
        if (stepLabel) {
            const tpl = stepLabel.dataset.template || 'Step ' + currentStep + ' of ' + totalSteps;
            stepLabel.textContent = tpl.replace(':current', currentStep).replace(':total', totalSteps);
        }
        steps.forEach((el, i) => {
            el.classList.toggle('hidden', i + 1 !== currentStep);
        });
        if (progressBar) progressBar.style.width = (currentStep / totalSteps) * 100 + '%';

        const prevBtn = document.getElementById('prev-btn');
        const logoBtn = document.getElementById('logo-btn');
        if (prevBtn) prevBtn.classList.toggle('hidden', currentStep <= 1);
        if (logoBtn) logoBtn.classList.toggle('hidden', currentStep > 1);
    }

    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    if (prevBtn) prevBtn.addEventListener('click', () => showStep(currentStep - 1));
    if (nextBtn) nextBtn.addEventListener('click', () => showStep(currentStep + 1));

    document.querySelectorAll('[data-goto]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const intentInput = document.querySelector('[name="intent"]');
            if (intentInput) intentInput.value = btn.dataset.intent || '';
            showStep(currentStep + 1);
        });
    });

    document.querySelectorAll('[data-atd]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-atd]').forEach((b) => {
                b.classList.remove('border-brand-600', 'bg-brand-50', 'text-brand-600');
                b.classList.add('border-slate-50', 'bg-slate-50', 'text-slate-400');
            });
            btn.classList.remove('border-slate-50', 'bg-slate-50', 'text-slate-400');
            btn.classList.add('border-brand-600', 'bg-brand-50', 'text-brand-600');
            const atdLicense = document.querySelector('[name="atd_license"]');
            if (atdLicense) atdLicense.value = btn.dataset.atd || '';
            checkStep3();
        });
    });

    document.querySelectorAll('[data-exp]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-exp]').forEach((b) => {
                b.classList.remove('border-brand-600', 'bg-brand-50', 'text-brand-600');
                b.classList.add('border-slate-50', 'bg-slate-50', 'text-slate-400');
            });
            btn.classList.remove('border-slate-50', 'bg-slate-50', 'text-slate-400');
            btn.classList.add('border-brand-600', 'bg-brand-50', 'text-brand-600');
            const drivingExp = document.querySelector('[name="driving_experience"]');
            if (drivingExp) drivingExp.value = btn.dataset.exp || '';
            checkStep3();
        });
    });

    function checkStep3() {
        const atd = document.querySelector('[name="atd_license"]');
        const exp = document.querySelector('[name="driving_experience"]');
        const nextBtn3 = document.getElementById('next-btn-3');
        if (nextBtn3) nextBtn3.disabled = !(atd && atd.value && exp && exp.value);
    }

    const nextBtn3 = document.getElementById('next-btn-3');
    if (nextBtn3) nextBtn3.addEventListener('click', () => showStep(4));

    document.querySelectorAll('[name="name"], [name="area"]').forEach((inp) => {
        inp.addEventListener('input', () => {
            const n = document.querySelector('[name="name"]');
            const a = document.querySelector('[name="area"]');
            const nextBtn4 = document.getElementById('next-btn-4');
            if (nextBtn4) nextBtn4.disabled = !(n && n.value.trim() && a && a.value.trim());
        });
    });

    const nextBtn4 = document.getElementById('next-btn-4');
    if (nextBtn4) nextBtn4.addEventListener('click', () => showStep(5));

    const submitBtn = document.getElementById('submit-btn');
    if (submitBtn) submitBtn.addEventListener('click', () => form.submit());

    const phoneInput = document.querySelector('[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            const nextBtn = document.getElementById('next-btn');
            if (nextBtn) nextBtn.disabled = this.value.replace(/\D/g, '').length < 8;
        });
    }

    const startStepInput = document.getElementById('apply-start-step');
    const startStep = startStepInput ? parseInt(startStepInput.value, 10) : 1;
    showStep(isNaN(startStep) ? 1 : Math.max(1, Math.min(startStep, totalSteps)));
});

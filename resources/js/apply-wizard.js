document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('apply-form');
    if (!form) return;

    const FLOW_WORK = ['phone', 'path', 'qual', 'latvian', 'shifts', 'details', 'ready'];
    const FLOW_RENT = ['phone', 'path', 'qual', 'details', 'ready'];

    const progressBar = document.getElementById('progress-bar');
    const stepLabel = document.getElementById('step-label');
    const stepInput = document.getElementById('step-input');
    const intentInput = document.getElementById('intent-input');

    let flowIndex = 0;

    function getIntent() {
        return intentInput?.value || '';
    }

    function getFlow() {
        return getIntent() === 'work' ? FLOW_WORK : FLOW_RENT;
    }

    function showFlowStep(index) {
        const flow = getFlow();
        const last = flow.length - 1;
        flowIndex = Math.max(0, Math.min(index, last));
        const panel = flow[flowIndex];

        document.querySelectorAll('[data-flow-panel]').forEach((el) => {
            el.classList.toggle('hidden', el.dataset.flowPanel !== panel);
        });

        const total = flow.length;
        const current = flowIndex + 1;
        if (stepInput) stepInput.value = String(current);
        if (stepLabel) {
            const tpl = stepLabel.dataset.template || 'Step :current of :total';
            stepLabel.textContent = tpl.replace(':current', String(current)).replace(':total', String(total));
        }
        if (progressBar) progressBar.style.width = (current / total) * 100 + '%';

        const prevBtn = document.getElementById('prev-btn');
        const logoBtn = document.getElementById('logo-btn');
        if (prevBtn) prevBtn.classList.toggle('hidden', flowIndex <= 0);
        if (logoBtn) logoBtn.classList.toggle('hidden', flowIndex > 0);
    }

    const prevBtn = document.getElementById('prev-btn');
    if (prevBtn) {
        prevBtn.addEventListener('click', () => showFlowStep(flowIndex - 1));
    }

    const nextBtn = document.getElementById('next-btn');
    if (nextBtn) nextBtn.addEventListener('click', () => showFlowStep(1));

    document.querySelectorAll('[data-goto]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (intentInput) intentInput.value = btn.dataset.intent || '';
            showFlowStep(2);
        });
    });

    function atdLicenseValue() {
        return document.querySelector('[name="atd_license"]')?.value || '';
    }

    function checkQualContinue() {
        const atd = atdLicenseValue();
        const exp = document.querySelector('[name="driving_experience"]')?.value || '';
        const atdNum = document.querySelector('[name="atd_number"]')?.value?.trim() || '';
        const needCard = atd === 'yes';
        const cardOk = !needCard || atdNum.length > 0;
        const nextBtnQual = document.getElementById('next-btn-qual');
        if (nextBtnQual) nextBtnQual.disabled = !(atd && exp && cardOk);
    }

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
            checkQualContinue();
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
            checkQualContinue();
        });
    });

    document.querySelector('[name="atd_number"]')?.addEventListener('input', checkQualContinue);

    document.querySelectorAll('[data-latvian]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-latvian]').forEach((b) => {
                b.classList.remove('border-brand-600', 'bg-brand-50', 'text-brand-600');
                b.classList.add('border-slate-50', 'bg-slate-50', 'text-slate-400');
            });
            btn.classList.remove('border-slate-50', 'bg-slate-50', 'text-slate-400');
            btn.classList.add('border-brand-600', 'bg-brand-50', 'text-brand-600');
            const h = document.querySelector('[name="latvian_b1"]');
            if (h) h.value = btn.dataset.latvian || '';
            const nextLatvian = document.getElementById('next-btn-latvian');
            if (nextLatvian) nextLatvian.disabled = false;
        });
    });

    document.querySelectorAll('[data-shift]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-shift]').forEach((b) => {
                b.classList.remove('border-brand-600', 'bg-brand-50', 'text-brand-600');
                b.classList.add('border-slate-50', 'bg-slate-50', 'text-slate-400');
            });
            btn.classList.remove('border-slate-50', 'bg-slate-50', 'text-slate-400');
            btn.classList.add('border-brand-600', 'bg-brand-50', 'text-brand-600');
            const h = document.querySelector('[name="shift_preference"]');
            if (h) h.value = btn.dataset.shift || '';
            const nextShifts = document.getElementById('next-btn-shifts');
            if (nextShifts) nextShifts.disabled = false;
        });
    });

    const nextBtnQual = document.getElementById('next-btn-qual');
    if (nextBtnQual) {
        nextBtnQual.addEventListener('click', () => {
            if (getIntent() === 'work') {
                showFlowStep(getFlow().indexOf('latvian'));
            } else {
                showFlowStep(getFlow().indexOf('details'));
            }
        });
    }

    const nextBtnLatvian = document.getElementById('next-btn-latvian');
    if (nextBtnLatvian) {
        nextBtnLatvian.addEventListener('click', () => showFlowStep(getFlow().indexOf('shifts')));
    }

    const nextBtnShifts = document.getElementById('next-btn-shifts');
    if (nextBtnShifts) {
        nextBtnShifts.addEventListener('click', () => showFlowStep(getFlow().indexOf('details')));
    }

    function checkDetailsContinue() {
        const n = document.querySelector('[name="name"]');
        const e = document.querySelector('[name="email"]');
        const a = document.querySelector('[name="area"]');
        const emailVal = e?.value?.trim() || '';
        const emailOk = emailVal.length > 3 && emailVal.includes('@');
        const nextBtnDetails = document.getElementById('next-btn-details');
        if (nextBtnDetails) {
            nextBtnDetails.disabled = !(n?.value?.trim() && emailOk && a?.value?.trim());
        }
    }

    document.querySelectorAll('[name="name"], [name="email"], [name="area"]').forEach((inp) => {
        inp.addEventListener('input', checkDetailsContinue);
    });

    const nextBtnDetails = document.getElementById('next-btn-details');
    if (nextBtnDetails) {
        nextBtnDetails.addEventListener('click', () => showFlowStep(getFlow().indexOf('ready')));
    }

    const submitBtn = document.getElementById('submit-btn');
    if (submitBtn) submitBtn.addEventListener('click', () => form.submit());

    const phoneInput = document.querySelector('[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            const nb = document.getElementById('next-btn');
            if (nb) nb.disabled = this.value.replace(/\D/g, '').length < 8;
        });
    }

    const startIdxEl = document.getElementById('apply-start-flow-index');
    let startIdx = startIdxEl ? parseInt(startIdxEl.value, 10) : 0;
    if (isNaN(startIdx)) startIdx = 0;
    showFlowStep(startIdx);
    checkQualContinue();
    checkDetailsContinue();
});

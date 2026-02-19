document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.faq-tab');
    const items = document.querySelectorAll('.faq-item');
    const triggers = document.querySelectorAll('.faq-trigger');
    const searchInput = document.querySelector('#faq-search');
    const pageEl = document.getElementById('faq-page');

    let currentCategory = pageEl?.dataset.defaultCategory || 'general';
    let searchQuery = '';

    const filterFAQ = () => {
        items.forEach(item => {
            const categoryMatch = item.dataset.category === currentCategory;
            const triggerSpan = item.querySelector('.faq-trigger span');
            const questionText = triggerSpan ? triggerSpan.textContent.toLowerCase() : '';
            const searchMatch = !searchQuery || questionText.includes(searchQuery.toLowerCase());

            if (categoryMatch && searchMatch) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    };

    // Tab Switching
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => {
                t.classList.remove('active', 'bg-slate-900', 'text-white', 'border-slate-900');
                t.classList.add('bg-white', 'text-slate-500', 'border-slate-200');
            });

            tab.classList.add('active', 'bg-slate-900', 'text-white', 'border-slate-900');
            tab.classList.remove('bg-white', 'text-slate-500', 'border-slate-200');

            currentCategory = tab.dataset.category;
            filterFAQ();
            closeAllAccordions();

            if (window.history.replaceState) {
                window.history.replaceState(null, '', '#' + currentCategory);
            }
        });
    });

    // Search Filtering
    searchInput?.addEventListener('input', (e) => {
        searchQuery = e.target.value || '';
        filterFAQ();
    });

    // Accordion Logic
    triggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
            const item = trigger.closest('.faq-item');
            const content = item.querySelector('.faq-content');
            const chevron = item.querySelector('.faq-chevron');
            const question = item.querySelector('.faq-question');
            const isOpen = item.classList.contains('faq-open');

            closeAllAccordions();

            if (!isOpen && content && chevron) {
                item.classList.add('faq-open');
                item.classList.remove('border-slate-100', 'hover:border-slate-200');
                item.classList.add('border-brand-600/20', 'shadow-xl', 'shadow-brand-600/5', 'ring-1', 'ring-brand-600/5');
                content.style.maxHeight = content.scrollHeight + 'px';
                chevron.classList.remove('bg-slate-50', 'text-slate-300');
                chevron.classList.add('bg-brand-600', 'text-white', 'rotate-180', 'shadow-md', 'shadow-brand-600/30');
                if (question) {
                    question.classList.remove('text-slate-600');
                    question.classList.add('text-slate-900');
                }
            }
        });
    });

    function closeAllAccordions() {
        items.forEach(item => {
            item.classList.remove('faq-open', 'border-brand-600/20', 'shadow-xl', 'shadow-brand-600/5', 'ring-1', 'ring-brand-600/5');
            item.classList.add('border-slate-100', 'hover:border-slate-200');
            const content = item.querySelector('.faq-content');
            if (content) content.style.maxHeight = '0px';
            const chevron = item.querySelector('.faq-chevron');
            if (chevron) {
                chevron.classList.remove('bg-brand-600', 'text-white', 'rotate-180', 'shadow-md', 'shadow-brand-600/30');
                chevron.classList.add('bg-slate-50', 'text-slate-300');
            }
            const question = item.querySelector('.faq-question');
            if (question) {
                question.classList.remove('text-slate-900');
                question.classList.add('text-slate-600');
            }
        });
    }

    filterFAQ();

    if (window.location.hash) {
        const hash = window.location.hash.slice(1);
        const tab = document.querySelector('.faq-tab[data-category="' + hash + '"]');
        if (tab) tab.click();
    }
});

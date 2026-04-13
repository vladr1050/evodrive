/**
 * FAQ tabs + search only. Accordion uses native <details> in the Blade template
 * so Q/A still expands if this file fails to load (CDN/cache/adblock).
 */
function bootFaqPage() {
    const tabs = document.querySelectorAll('.faq-tab');
    const items = document.querySelectorAll('.faq-item');
    const searchInput = document.querySelector('#faq-search');
    const pageEl = document.getElementById('faq-page');

    let currentCategory = pageEl?.dataset.defaultCategory || 'general';
    let searchQuery = '';

    const filterFAQ = () => {
        items.forEach((item) => {
            const categoryMatch = item.dataset.category === currentCategory;
            const questionEl = item.querySelector('.faq-question');
            const questionText = questionEl ? questionEl.textContent.toLowerCase() : '';
            const searchMatch = !searchQuery || questionText.includes(searchQuery.toLowerCase());

            // Tailwind .hidden uses display:none !important — toggle class, not inline style.
            if (categoryMatch && searchMatch) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });
    };

    function closeAllDetails() {
        items.forEach((item) => {
            const det = item.querySelector('details');
            if (det) {
                det.open = false;
            }
        });
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tabs.forEach((t) => {
                t.classList.remove('active', 'bg-slate-900', 'text-white', 'border-slate-900');
                t.classList.add('bg-white', 'text-slate-500', 'border-slate-200');
            });

            tab.classList.add('active', 'bg-slate-900', 'text-white', 'border-slate-900');
            tab.classList.remove('bg-white', 'text-slate-500', 'border-slate-200');

            currentCategory = tab.dataset.category;
            filterFAQ();
            closeAllDetails();

            if (window.history.replaceState) {
                window.history.replaceState(null, '', '#' + currentCategory);
            }
        });
    });

    searchInput?.addEventListener('input', (e) => {
        searchQuery = e.target.value || '';
        filterFAQ();
    });

    filterFAQ();

    if (window.location.hash) {
        const hash = window.location.hash.slice(1);
        const tab = document.querySelector('.faq-tab[data-category="' + hash + '"]');
        if (tab) tab.click();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootFaqPage, { once: true });
} else {
    bootFaqPage();
}

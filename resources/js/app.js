import './bootstrap';

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('navbar-menu-toggle')?.addEventListener('click', function () {
        const menu = document.getElementById('navbar-mobile-menu');
        const iconMenu = document.getElementById('navbar-icon-menu');
        const iconX = document.getElementById('navbar-icon-x');
        menu?.classList.toggle('hidden');
        iconMenu?.classList.toggle('hidden');
        iconX?.classList.toggle('hidden');
        this.setAttribute('aria-expanded', menu?.classList.contains('hidden') ? 'false' : 'true');
    });
});

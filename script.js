document.addEventListener('DOMContentLoaded', () => {
    const welcome = document.querySelector('.welcome');

    if (welcome) {
        welcome.style.transition = 'transform 0.3s ease';

        welcome.addEventListener('mouseenter', () => {
            welcome.style.transform = 'scale(1.02)';
        });

        welcome.addEventListener('mouseleave', () => {
            welcome.style.transform = 'scale(1)';
        });
    }
});

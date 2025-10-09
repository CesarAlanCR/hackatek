// Animaciones premium y micro-interacciones para Hackatek
// Archivo: recursos/js/animations.js

(function() {
    'use strict';

    // Partículas flotantes de fondo (efecto sutil)
    function createParticles() {
        const canvas = document.createElement('canvas');
        canvas.style.position = 'fixed';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.pointerEvents = 'none';
        canvas.style.zIndex = '0';
        canvas.style.opacity = '0.15';
        document.body.prepend(canvas);

        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const particles = [];
        for (let i = 0; i < 50; i++) {
            particles.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                radius: Math.random() * 2 + 1,
                speedX: (Math.random() - 0.5) * 0.5,
                speedY: (Math.random() - 0.5) * 0.5,
                opacity: Math.random() * 0.5 + 0.2
            });
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(p => {
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(124, 179, 66, ${p.opacity})`;
                ctx.fill();

                p.x += p.speedX;
                p.y += p.speedY;

                if (p.x < 0 || p.x > canvas.width) p.speedX *= -1;
                if (p.y < 0 || p.y > canvas.height) p.speedY *= -1;
            });
            requestAnimationFrame(animate);
        }
        animate();

        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });
    }

    // Fade-in escalonado premium para módulos
    function animateModules() {
        const modules = document.querySelectorAll('.module-card');
        modules.forEach((module, index) => {
            module.style.opacity = '0';
            module.style.transform = 'translateY(40px) scale(0.9)';
            setTimeout(() => {
                module.style.transition = 'opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1), transform 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                module.style.opacity = '1';
                module.style.transform = 'translateY(0) scale(1)';
            }, 150 * index);
        });
    }

    // Efecto ripple premium en botones
    function addRippleEffect() {
        const buttons = document.querySelectorAll('.btn, .btn-primary');
        buttons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                ripple.classList.add('ripple');
                const rect = btn.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = e.clientX - rect.left - size / 2 + 'px';
                ripple.style.top = e.clientY - rect.top - size / 2 + 'px';
                btn.appendChild(ripple);
                setTimeout(() => ripple.remove(), 800);
            });
        });
    }

    // Efecto parallax suave en hero
    function addParallaxEffect() {
        const hero = document.querySelector('.hero');
        if (!hero) return;

        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            if (scrolled < 500) {
                hero.style.transform = `translateY(${scrolled * 0.4}px)`;
                hero.style.opacity = 1 - scrolled / 600;
            }
        });
    }

    // Efecto hover magnético en cards (desactivado para no interferir con clicks)
    function addMagneticEffect() {
        // Comentado temporalmente - interfiere con navegación de links
        return;
        /*
        const cards = document.querySelectorAll('.module-card');
        cards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                
                card.style.transform = `perspective(1000px) rotateX(${y / 20}deg) rotateY(${x / 20}deg) translateY(-12px) scale(1.03)`;
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = '';
            });
        });
        */
    }

    // Smooth scroll para enlaces internos
    function enableSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    // Observador de intersección para animaciones al hacer scroll
    function observeElements() {
        if (!('IntersectionObserver' in window)) return;
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.card, .hero, .preview-clima').forEach(el => {
            observer.observe(el);
        });
    }

    // Cursor personalizado (opcional, muy sutil)
    function customCursor() {
        const cursor = document.createElement('div');
        cursor.className = 'custom-cursor';
        document.body.appendChild(cursor);

        document.addEventListener('mousemove', (e) => {
            cursor.style.left = e.clientX + 'px';
            cursor.style.top = e.clientY + 'px';
        });

        document.querySelectorAll('a, button, .module-card').forEach(el => {
            el.addEventListener('mouseenter', () => cursor.classList.add('cursor-hover'));
            el.addEventListener('mouseleave', () => cursor.classList.remove('cursor-hover'));
        });
    }

    // Inicializar al cargar DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        createParticles();
        animateModules();
        addRippleEffect();
        addParallaxEffect();
        addMagneticEffect();
        enableSmoothScroll();
        observeElements();
        // customCursor(); // Descomentar si deseas cursor personalizado
    }

})();

// Estilos CSS inline para efectos premium
const premiumStyles = document.createElement('style');
premiumStyles.textContent = `
    .btn, .btn-primary { position: relative; overflow: hidden; }
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255,255,255,0.7);
        transform: scale(0);
        animation: ripple-animation 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: none;
    }
    @keyframes ripple-animation {
        to { transform: scale(3); opacity: 0; }
    }
    .in-view {
        animation: fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }
    .custom-cursor {
        position: fixed;
        width: 20px;
        height: 20px;
        border: 2px solid rgba(124, 179, 66, 0.6);
        border-radius: 50%;
        pointer-events: none;
        transform: translate(-50%, -50%);
        transition: width 0.2s, height 0.2s, border-color 0.2s;
        z-index: 9999;
        mix-blend-mode: difference;
        display: none; /* hidden by default; enable with customCursor() */
    }
    .custom-cursor.cursor-hover {
        width: 40px;
        height: 40px;
        border-color: rgba(124, 179, 66, 1);
    }
    /* Keep native cursor visible by default; interactive elements use pointer */
    body { cursor: auto; }
    a, button, .module-card { cursor: pointer; }
    @media (max-width: 768px) {
        body { cursor: auto; }
        a, button, .module-card { cursor: pointer; }
        .custom-cursor { display: none; }
    }
`;
document.head.appendChild(premiumStyles);

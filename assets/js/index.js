/* ── Sticky nav ── */
const nav = document.getElementById('topNav');
window.addEventListener('scroll', () => {
  nav.classList.toggle('scrolled', window.scrollY > 10);
});

/* ── Hero Carousel ── */
(function () {
  const track = document.getElementById('hcTrack');
  const prevBtn = document.getElementById('hcPrev');
  const nextBtn = document.getElementById('hcNext');
  const dotsWrap = document.getElementById('hcDots');

  const cards = Array.from(track.children);
  const CARD_H = 240;  // px — matches .hc-card height in CSS
  const STEP = CARD_H;
  let current = 0;
  let autoTimer;

  /* Build dots */
  cards.forEach((_, i) => {
    const d = document.createElement('button');
    d.className = 'hc-dot' + (i === 0 ? ' hc-dot--active' : '');
    d.setAttribute('aria-label', 'Go to slide ' + (i + 1));
    d.addEventListener('click', () => goTo(i));
    dotsWrap.appendChild(d);
  });
  const dots = Array.from(dotsWrap.children);

  function goTo(idx) {
    current = Math.max(0, Math.min(idx, cards.length - 1));
    track.style.transform = `translateY(-${current * STEP}px)`;
    dots.forEach((d, i) => d.classList.toggle('hc-dot--active', i === current));
    prevBtn.disabled = current === 0;
    nextBtn.disabled = current === cards.length - 1;
  }

  prevBtn.addEventListener('click', () => { resetAuto(); goTo(current - 1); });
  nextBtn.addEventListener('click', () => { resetAuto(); goTo(current + 1); });

  function autoPlay() {
    goTo(current < cards.length - 1 ? current + 1 : 0);
  }

  function resetAuto() {
    clearInterval(autoTimer);
    autoTimer = setInterval(autoPlay, 3000);
  }

  goTo(0);
  autoTimer = setInterval(autoPlay, 3000);

  /* Pause on hover */
  track.closest('.hc-track-outer').addEventListener('mouseenter', () => clearInterval(autoTimer));
  track.closest('.hc-track-outer').addEventListener('mouseleave', () => resetAuto());
})();

/* ── Auth Modal ── */
(function () {
  const triggers = document.querySelectorAll('.js-auth-trigger');
  const modal = document.getElementById('authModal');
  const closeBtn = document.getElementById('authModalClose');

  if (!modal) return;

  triggers.forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      modal.classList.add('active');
    });
  });

  closeBtn.addEventListener('click', () => {
    modal.classList.remove('active');
  });

  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      modal.classList.remove('active');
    }
  });
})();
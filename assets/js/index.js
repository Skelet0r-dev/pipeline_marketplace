(function () {
    var DURATION = 3500;
    var cards    = document.querySelectorAll('.carousel-card');
    var dotsWrap = document.getElementById('carouselDots');
    var progEl   = document.getElementById('carouselProgress');
    var ctrEl    = document.getElementById('carouselCounter');
    var cur      = 0;
    var startTime = null;
    var raf       = null;
 
    /* Build dot buttons */
    cards.forEach(function (_, i) {
      var btn = document.createElement('button');
      btn.className = 'dot' + (i === 0 ? ' active' : '');
      btn.setAttribute('aria-label', 'Go to slide ' + (i + 1));
      btn.addEventListener('click', function () { goTo(i); resetTimer(); });
      dotsWrap.insertBefore(btn, ctrEl);
    });
 
    function getDots() { return dotsWrap.querySelectorAll('.dot'); }
 
    function goTo(n) {
      /* Exit current */
      cards[cur].classList.remove('active');
      cards[cur].classList.add('exit');
      getDots()[cur].classList.remove('active');
 
      /* Clean up exit class after transition */
      (function (idx) {
        setTimeout(function () { cards[idx].classList.remove('exit'); }, 520);
      }(cur));
 
      cur = (n + cards.length) % cards.length;
 
      cards[cur].classList.add('active');
      getDots()[cur].classList.add('active');
      ctrEl.textContent = (cur + 1) + ' / ' + cards.length;
    }
 
    function resetTimer() {
      startTime = null;
      progEl.style.width = '0%';
    }
 
    function tick(ts) {
      if (!startTime) startTime = ts;
      var elapsed = ts - startTime;
      var pct = Math.min((elapsed / DURATION) * 100, 100);
      progEl.style.width = pct.toFixed(1) + '%';
 
      if (elapsed >= DURATION) {
        goTo(cur + 1);
        startTime = ts;
      }
      raf = requestAnimationFrame(tick);
    }
 
    raf = requestAnimationFrame(tick);
 
    /* Pause on hover */
    var col = document.querySelector('.hero-image');
    col.addEventListener('mouseenter', function () {
      cancelAnimationFrame(raf);
    });
    col.addEventListener('mouseleave', function () {
      startTime = null;
      raf = requestAnimationFrame(tick);
    });
  }());

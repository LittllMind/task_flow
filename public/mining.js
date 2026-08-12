/* Swipe + fallback clics pour le Mining Deck */
(function () {
  'use strict';

  const stack = document.querySelector('.mining-stack');
  if (!stack) return;

  let startX = 0, startY = 0, curX = 0, curY = 0, dragging = false;
  let activeCard = null, raf = null, hintEl = document.getElementById('miningHint');

  function getActiveCard() {
    return stack.querySelector('.mining-card.active');
  }

  function submitForm(action) {
    const card = getActiveCard();
    if (!card) return;
    const id = card.dataset.itemId;
    if (!id) return;

    // CSRF token is global in the page (first hidden input)
    const csrf = document.querySelector('input[name="csrf"]')?.value || '';
    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'mining.php';
    form.innerHTML = `
      <input type="hidden" name="csrf" value="${csrf}">
      <input type="hidden" name="action" value="${action}">
      <input type="hidden" name="id" value="${id}">
    `;
    document.body.appendChild(form);
    if (action === 'delete' && !confirm('Supprimer cette action ?')) {
      form.remove();
      resetCard();
      return;
    }
    form.submit();
  }

  function resetCard() {
    if (activeCard) {
      activeCard.style.transform = '';
      activeCard.style.opacity = '';
      activeCard.classList.remove('swiping');
      activeCard = null;
    }
    if (hintEl) hintEl.classList.remove('show-skip', 'show-done');
  }

  function updateCard(deltaX, deltaY, progress) {
    if (!activeCard) return;
    const rotate = deltaX * 0.05;
    const opacity = Math.max(0.25, 1 - progress * 0.7);
    activeCard.style.transform = `translate(${deltaX}px, ${deltaY}px) rotate(${rotate}deg)`;
    activeCard.style.opacity = String(opacity);

    if (hintEl) {
      hintEl.classList.toggle('show-skip', deltaX < -60);
      hintEl.classList.toggle('show-done', deltaX > 60);
      hintEl.textContent = deltaY > 80 ? '↓ suppr' : '← skip · → done · ↓ suppr';
    }
  }

  function onPointerDown(e) {
    activeCard = getActiveCard();
    if (!activeCard) return;
    if (e.target.closest('button, a, input')) return; // laisser les clics boutons

    dragging = true;
    startX = e.clientX || e.touches?.[0]?.clientX || 0;
    startY = e.clientY || e.touches?.[0]?.clientY || 0;
    activeCard.classList.add('swiping');
    activeCard.style.transition = 'none';
  }

  function onPointerMove(e) {
    if (!dragging || !activeCard) return;
    e.preventDefault();

    const cx = e.clientX || e.touches?.[0]?.clientX || 0;
    const cy = e.clientY || e.touches?.[0]?.clientY || 0;
    curX = cx - startX;
    curY = cy - startY;

    const max = Math.max(window.innerWidth, 360);
    const progress = Math.min(Math.abs(curX) / (max * 0.4), 1);

    if (raf) cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => updateCard(curX, curY, progress));
  }

  function onPointerUp(e) {
    if (!dragging || !activeCard) return;
    dragging = false;
    activeCard.style.transition = 'transform .25s ease, opacity .25s ease';

    const thresholdX = 80;
    const thresholdY = 90;

    if (curY > thresholdY) {
      // Swipe bas = destroy
      submitForm('delete');
    } else if (curX > thresholdX) {
      // Swipe droit = done/harvest
      submitForm('done');
    } else if (curX < -thresholdX) {
      // Swipe gauche = skip
      submitForm('skip');
    } else {
      resetCard();
    }
  }

  // Touch
  stack.addEventListener('touchstart', onPointerDown, { passive: false });
  stack.addEventListener('touchmove', onPointerMove, { passive: false });
  stack.addEventListener('touchend', onPointerUp);

  // Mouse (desktop de secours)
  stack.addEventListener('mousedown', onPointerDown);
  window.addEventListener('mousemove', onPointerMove);
  window.addEventListener('mouseup', onPointerUp);

  // Vibration subtile au toucher
  stack.addEventListener('touchstart', () => {
    if (navigator.vibrate) navigator.vibrate(8);
  }, { passive: true });
})();

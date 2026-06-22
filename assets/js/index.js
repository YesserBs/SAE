document.addEventListener('DOMContentLoaded', () => {

  /* ── Auto-soumission des filtres checkboxes ── */
  document.querySelectorAll('aside.filters input[type=checkbox]').forEach(cb => {
    cb.addEventListener('change', () => cb.closest('form').submit());
  });

  /* ── Auto-soumission du curseur rayon (au relâchement seulement) ── */
  const rayonInput = document.getElementById('f-rayon');
  if (rayonInput) {
    rayonInput.addEventListener('change', () => rayonInput.closest('form').submit());
  }

  animateStatsCounter();
  observeOfferCards();
  rotatingPlaceholder();
  buildJobTicker();
  pulseCTA();
});

/* ── Compteur animé sur "X offres actives" ── */
function animateStatsCounter() {
  document.querySelectorAll('.stats-bar strong').forEach(el => {
    const target = parseInt(el.textContent.replace(/\D/g, ''), 10) || 0;
    let current = 0;
    const step = Math.max(1, Math.ceil(target / 40));
    const tick = () => {
      current = Math.min(target, current + step);
      el.textContent = current;
      if (current < target) requestAnimationFrame(tick);
    };
    tick();
  });
}

/* ── Apparition progressive des cartes au scroll ── */
function observeOfferCards() {
  const cards = document.querySelectorAll('.offer-card');
  if (!cards.length) return;
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        setTimeout(() => entry.target.classList.add('is-visible'), i * 60);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });
  cards.forEach(card => observer.observe(card));
}

/* ── Placeholder rotatif dans la barre de recherche ── */
function rotatingPlaceholder() {
  const input = document.getElementById('search-q');
  if (!input || input.value) return;
  const exemples = [
    'Développeur Full-Stack...', 'Chef de projet digital...',
    'Designer UX/UI...', 'Data Analyst...',
    'Alternance Marketing...', 'Stage Ressources Humaines...',
    'DevOps Cloud...', 'Product Manager...'
  ];
  let i = 0;
  setInterval(() => {
    i = (i + 1) % exemples.length;
    input.setAttribute('placeholder', exemples[i]);
  }, 2600);
}

/* ── Bandeau défilant type ticker emploi ── */
function buildJobTicker() {
  const hero = document.querySelector('.hero');
  if (!hero || document.querySelector('.job-ticker')) return;
  const messages = [
    '📢 + de 500 candidats ont trouvé un emploi via SearchForAJob ce mois-ci',
    '🔥 Nouvelles offres ajoutées chaque jour',
    '🚀 Postulez en 1 clic et suivez vos candidatures en temps réel',
    '💼 Recruteurs : publiez votre offre gratuitement',
    '🌍 Offres en France et en full remote'
  ];
  const ticker = document.createElement('div');
  ticker.className = 'job-ticker';
  const track = messages.join(' &nbsp;•&nbsp; ');
  ticker.innerHTML = `<div class="job-ticker__track">${track} &nbsp;•&nbsp; ${track}</div>`;
  hero.insertAdjacentElement('afterend', ticker);
}

/* ── Pulsation du CTA principal ── */
function pulseCTA() {
  const cta = document.querySelector('.btn-publier');
  if (cta) cta.classList.add('pulse');
}

/* ── Bookmark : icône + toast ── */
function toggleBookmark(btn) {
  const icon = btn.querySelector('i');
  const actif = icon.classList.contains('bi-bookmark-fill');
  icon.classList.toggle('bi-bookmark',      actif);
  icon.classList.toggle('bi-bookmark-fill', !actif);
  btn.classList.add('bumped');
  setTimeout(() => btn.classList.remove('bumped'), 400);
  showToast(actif ? 'Offre retirée des favoris' : '✓ Offre ajoutée aux favoris');
}

function showToast(message) {
  let toast = document.querySelector('.toast-job');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'toast-job';
    document.body.appendChild(toast);
  }
  toast.innerHTML = `<i class="bi bi-check-circle-fill"></i> ${message}`;
  requestAnimationFrame(() => toast.classList.add('show'));
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => toast.classList.remove('show'), 2400);
}

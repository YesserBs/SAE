 function toggleBookmark(btn) {
    const icon = btn.querySelector('i');
    const saved = icon.classList.contains('bi-bookmark-fill');
    icon.classList.toggle('bi-bookmark', saved);
    icon.classList.toggle('bi-bookmark-fill', !saved);
    btn.style.color = saved ? '' : '#185FA5';
  }
  // Soumettre les filtres automatiquement au changement de checkbox
  document.querySelectorAll('aside.filters input[type=checkbox]').forEach(cb => {
    cb.addEventListener('change', () => cb.closest('form').submit());
  });
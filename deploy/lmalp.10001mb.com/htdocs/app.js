const catSelect = document.getElementById('category');
const subSelect = document.getElementById('subcategory');
const modal = document.getElementById('modal');
const openBtn = document.getElementById('openModal');
const closeBtn = document.getElementById('closeModal');

function updateSubcategories() {
  const cat = catSelect.value;
  subSelect.innerHTML = '<option value="">Sous-catégorie (optionnel)</option>';
  if (!cat || !subcats[cat]) return;
  subcats[cat].forEach(s => {
    const opt = document.createElement('option');
    opt.value = s;
    opt.textContent = s;
    subSelect.appendChild(opt);
  });
}

catSelect.addEventListener('change', updateSubcategories);

openBtn.addEventListener('click', () => modal.classList.add('open'));
closeBtn.addEventListener('click', e => {
  e.preventDefault();
  modal.classList.remove('open');
});
modal.addEventListener('click', e => {
  if (e.target === modal) modal.classList.remove('open');
});

// Auto-open modal if hash #add
if (location.hash === '#add') modal.classList.add('open');

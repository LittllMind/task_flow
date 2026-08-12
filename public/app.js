const catSelect = document.getElementById('category');
const subSelect = document.getElementById('subcategory');
const modal = document.getElementById('modal');
const openBtn = document.getElementById('openModal');
const closeBtn = document.getElementById('closeModal');
const form = document.getElementById('taskForm');
const formAction = document.getElementById('formAction');
const formId = document.getElementById('formId');
const modalTitle = document.getElementById('modalTitle');
const submitBtn = document.getElementById('submitBtn');

function fillSubcategories(selectedCat, selectedSub = '') {
  subSelect.innerHTML = '<option value="">Sous-catégorie (optionnel)</option>';
  if (!selectedCat || !subcats[selectedCat]) return;
  subcats[selectedCat].forEach(s => {
    const opt = document.createElement('option');
    opt.value = s;
    opt.textContent = s;
    if (s === selectedSub) opt.selected = true;
    subSelect.appendChild(opt);
  });
}

catSelect.addEventListener('change', () => fillSubcategories(catSelect.value));

function appOpenModal(mode = 'create') {
  modal.classList.add('open');
  if (mode === 'create') {
    form.reset();
    formAction.value = 'create';
    formId.value = '';
    modalTitle.textContent = 'Nouvelle tâche';
    submitBtn.textContent = 'Ajouter';
    fillSubcategories('');
    // Let deck.js clear blockerContextId after its own click listener updates the form
  }
}

openBtn.addEventListener('click', () => appOpenModal('create'));
closeBtn.addEventListener('click', e => {
  e.preventDefault();
  modal.classList.remove('open');
});
modal.addEventListener('click', e => {
  if (e.target === modal) modal.classList.remove('open');
});

// Task action menus
const menuToggles = document.querySelectorAll('.menu-btn');
menuToggles.forEach(btn => {
  btn.addEventListener('click', e => {
    e.stopPropagation();
    const id = btn.dataset.id;
    const menu = document.getElementById('menu-' + id);
    document.querySelectorAll('.task-menu').forEach(m => {
      if (m !== menu) m.classList.remove('open');
    });
    menu.classList.toggle('open');
  });
});
document.addEventListener('click', () => {
  document.querySelectorAll('.task-menu').forEach(m => m.classList.remove('open'));
});

// Edit mode prefill
if (window.editTask) {
  const t = window.editTask;
  modal.classList.add('open');
  formAction.value = 'update';
  formId.value = t.id;
  modalTitle.textContent = 'Modifier la tâche';
  submitBtn.textContent = 'Enregistrer';
  document.getElementById('title').value = t.title;
  catSelect.value = t.category;
  fillSubcategories(t.category, t.subcategory || '');
  document.getElementById('priority').value = t.priority;
  document.getElementById('due_at').value = t.due_at || '';
  // Hide + button in edit mode
  if (openBtn) openBtn.style.display = 'none';
}

// Keep Add Task button hidden when editing or when modal is open via URL
if (window.location.search.includes('action=edit')) {
  if (openBtn) openBtn.style.display = 'none';
}

document.addEventListener('keydown', e => {
  const tag = document.activeElement?.tagName?.toLowerCase();
  const isTyping = tag === 'input' || tag === 'textarea' || tag === 'select' || document.activeElement?.isContentEditable;
  if (e.key === '/' && !isTyping && modal && !modal.classList.contains('open') && (!window.editTask || !window.location.search.includes('action=edit'))) {
    e.preventDefault();
    appOpenModal('create');
  }
  if (e.key === 'Escape' && modal && modal.classList.contains('open')) {
    e.preventDefault();
    modal.classList.remove('open');
  }
});

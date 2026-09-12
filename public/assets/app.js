document.querySelector('#menuButton')?.addEventListener('click',()=>document.querySelector('#sidebar')?.classList.toggle('open'));
document.querySelectorAll('[data-modal]').forEach(button=>button.addEventListener('click',()=>document.querySelector('#'+button.dataset.modal)?.showModal()));
document.querySelectorAll('.close-modal').forEach(button=>button.addEventListener('click',()=>button.closest('dialog')?.close()));
document.querySelector('#employeeSearch')?.addEventListener('input',event=>{
  const term=event.target.value.toLocaleLowerCase('pt-BR');
  document.querySelectorAll('#employeeRows tr').forEach(row=>row.hidden=!row.textContent.toLocaleLowerCase('pt-BR').includes(term));
});


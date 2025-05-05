const toggleEmployeesCity = () => {
  const employees = document.querySelectorAll('.employees__item');
  const toggles = document.querySelectorAll('.employees__city-toggle');

  if (toggles.length > 0 && employees.length > 0) {
    let activeToggleIndex = 0;
    let activeCity = toggles[activeToggleIndex].dataset.city;
    toggles.forEach((toggle, index) => {
      toggle.addEventListener('click', (e) => {
        if (!toggle.classList.contains('employees__city-toggle--active')) {
          toggles[activeToggleIndex].classList.remove('employees__city-toggle--active');
          toggle.classList.add('employees__city-toggle--active');

          activeToggleIndex = index;
          activeCity = toggle.dataset.city;

          employees.forEach((employee) => {
            if (employee.dataset.city.indexOf(activeCity) !== -1) {
              employee.style.display = 'block';
            } else {
              employee.style.display = 'none';
            }
          })
        }
      })
    });
  }
}

export {toggleEmployeesCity};

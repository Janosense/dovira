const toggleEmployeesCity = () => {
  const cityToggle = document.querySelectorAll('.city-toggle__button');
  const items = document.querySelectorAll('ul[data-city-toggle-list] > li');

  if (cityToggle.length > 0 && items.length > 0) {
    let activeToggleIndex = 0;
    let activeCity = cityToggle[activeToggleIndex].dataset.city;
    cityToggle.forEach((toggle, index) => {

      toggle.addEventListener('click', (e) => {
        if (!toggle.classList.contains('city-toggle__button--active')) {
          cityToggle[activeToggleIndex].classList.remove('city-toggle__button--active');
          toggle.classList.add('city-toggle__button--active');

          activeToggleIndex = index;
          activeCity = toggle.dataset.city;

          items.forEach((employee) => {
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

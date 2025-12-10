const toggleCities = () => {
  const cityToggles = document.querySelectorAll('.city-toggle');

  if (cityToggles.length > 0) {

    cityToggles.forEach((toggle, index) => {
      if (toggle.nextElementSibling) {
        const items = toggle.nextElementSibling.querySelectorAll('li');

        toggle.addEventListener('click', (evt) => {
          if (evt.target.dataset.city) {
            toggle.querySelectorAll('button').forEach((button) => {
              button.classList.remove('city-toggle__button--active');
              evt.target.classList.add('city-toggle__button--active');
            });

            let activeCity = evt.target.dataset.city;
            items.forEach((item) => {
              if (item.dataset.city.indexOf(activeCity) !== -1) {
                item.style.display = 'block';
              } else {
                item.style.display = 'none';
              }
            })
          }
        })
      }
    });
  }
}

export {toggleCities};

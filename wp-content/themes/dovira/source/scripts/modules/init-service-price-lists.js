const STATE = {
  activeIndex: 0,
};
const serviceSearch = (priceLists) => {
  const input = document.querySelector('#service-search-input');
  if (input) {
    const resetButton = document.querySelector('#service-search-reset');
    const services = document.querySelectorAll('.service__price-item');

      input.addEventListener('input', (evt) => {

        if (input.value.length > 0) {
          resetButton.classList.add('active');
        } else {
          resetButton.classList.remove('active');
        }

        if (input.value.length >= 3) {
          services.forEach((service) => {
            if (!service.dataset.search.includes(input.value.toLowerCase())) {
              service.style.display = 'none';
            } else {
              service.style.display = document.documentElement.clientWidth >= 768 ? 'grid' : 'block';
            }
          });
        }

        if (input.value.length < 3) {
          services.forEach((service) => {
            service.style.display = document.documentElement.clientWidth >= 768 ? 'grid' : 'block';
          });
        }
      });

      resetButton.addEventListener('click', (evt) => {
        if (input.value === '') {
          input.focus();
        } else {
          input.value = '';
          services.forEach((service) => {
            service.style.display = document.documentElement.clientWidth >= 768 ? 'grid' : 'block';
          });
          resetButton.classList.remove('active');
        }
      });
    }

}


const initServicePriceLists = () => {
  const priceToggles = document.querySelectorAll('.service__prices-toggle');
  const priceLists = document.querySelectorAll('.service__price-list');

  if (priceToggles.length > 0 && priceLists.length > 0 && priceToggles.length === priceLists.length) {
    const loader = document.querySelector('.loader');

    priceToggles.forEach((toggle, index) => {
      toggle.addEventListener('click', (evt) => {
        if (!toggle.classList.contains('service__prices-toggle--active')) {
          loader.classList.add('loader--active');
          priceToggles[STATE.activeIndex].classList.remove('service__prices-toggle--active');
          priceToggles[index].classList.add('service__prices-toggle--active');
          priceLists[STATE.activeIndex].classList.remove('service__price-list--active');
          priceLists[index].classList.add('service__price-list--active');

          STATE.activeIndex = index;

          setTimeout(() => {
            loader.classList.remove('loader--active');
          }, 500);
        }
      });
    })

    serviceSearch(priceLists);
  }
}

export {initServicePriceLists}

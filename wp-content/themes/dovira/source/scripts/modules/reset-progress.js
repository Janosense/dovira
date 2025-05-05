const NAMESPACE = 'dovira/v1';
const REST_BASE = 'test';
const REST_BASE_PATH = `/wp-json/${NAMESPACE}/${REST_BASE}`;

const fetchData = async (data, endpoint) => {
  const response = await fetch(`${REST_BASE_PATH}/${endpoint}/`, {
    method: 'POST',
    body: data
  })
  if (response.ok) {
    return await response.json();
  }
}

const resetProgress = () => {
  const resetButton = document.querySelector('button#reset-progress');
  const loader = document.querySelector('.loader');
  if (resetButton) {
    resetButton.addEventListener('click', (evt) => {
      evt.preventDefault();
      loader.classList.add('loader--active');

      const formData = new FormData();
      formData.append('_wpnonce', resetButton.dataset.nonce);

      fetchData(formData, 'reset-progress').then((response) => {
        if (response.success) {
          window.location.reload();
        }
      });

    })
  }
}

export {resetProgress}

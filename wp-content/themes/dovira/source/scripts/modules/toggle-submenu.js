const toggleSubmenu = () => {
	const submenuToggles = document.querySelectorAll('.mobile-nav .menu-item__toggle');
	if (submenuToggles.length) {
		submenuToggles.forEach((submenuToggle) => {
			submenuToggle.addEventListener('click', (event) => {
				event.preventDefault();

				const menuItem = submenuToggle.closest('li');
				const submenu = menuItem.querySelector('.sub-menu');
				if (!submenu) {
					return;
				}

				const isOpen = menuItem.classList.toggle('menu-item--open');
				// 32px compensates the vertical padding added in the open state.
				submenu.style.maxHeight = isOpen ? `${submenu.scrollHeight + 32}px` : 0;
				submenuToggle.setAttribute('aria-expanded', isOpen);
			});
		});
	}
};

export { toggleSubmenu };

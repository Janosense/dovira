const toggleAccordion = () => {
	const accordioneItems = document.querySelectorAll('.accordion__item');
	if (accordioneItems.length) {
		accordioneItems.forEach((accordioneItem) => {
			accordioneItem.addEventListener('click', () => {
				if (!accordioneItem.classList.contains('accordion__item--active')) {
					const accordionText = accordioneItem.querySelector('.accordion__text');
					accordioneItem.querySelector('.accordion__text-holder').style.maxHeight = `${accordionText.clientHeight}px`;
				} else {
					accordioneItem.querySelector('.accordion__text-holder').style.maxHeight = 0;
				}

				accordioneItem.classList.toggle('accordion__item--active');
			});
		});
	}
};

export { toggleAccordion };

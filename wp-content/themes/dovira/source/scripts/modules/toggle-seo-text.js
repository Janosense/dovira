// Keep in sync with the max-height of .seo-text__content in source/styles/blocks/seo-text.css
const COLLAPSED_MAX_HEIGHT = 200;

const initSeoText = (holder) => {
	const content = holder.querySelector('.seo-text__content');
	const toggle = holder.querySelector('.seo-text__toggle');
	if (!content || !toggle) {
		return;
	}

	const labelCollapsed = toggle.dataset.labelCollapsed || toggle.textContent.trim();
	const labelExpanded = toggle.dataset.labelExpanded || labelCollapsed;

	const collapse = () => {
		content.classList.remove('seo-text__content--expanded');
		content.style.maxHeight = '';
		toggle.textContent = labelCollapsed;
		toggle.setAttribute('aria-expanded', 'false');
	};

	const expand = () => {
		content.classList.add('seo-text__content--expanded');
		content.style.maxHeight = `${content.scrollHeight}px`;
		toggle.textContent = labelExpanded;
		toggle.setAttribute('aria-expanded', 'true');
	};

	// scrollHeight always reports the full text height, whatever the current max-height is.
	const update = () => {
		const fits = content.scrollHeight <= COLLAPSED_MAX_HEIGHT;

		content.classList.toggle('seo-text__content--full', fits);
		toggle.hidden = fits;

		if (fits) {
			collapse();
		} else if (content.classList.contains('seo-text__content--expanded')) {
			content.style.maxHeight = `${content.scrollHeight}px`;
		}
	};

	toggle.addEventListener('click', () => {
		if (content.classList.contains('seo-text__content--expanded')) {
			collapse();
		} else {
			expand();
		}
	});

	update();

	// Images and web fonts land after the first measurement, reflow changes it again.
	window.addEventListener('load', update);

	let resizeTimeout;
	window.addEventListener('resize', () => {
		clearTimeout(resizeTimeout);
		resizeTimeout = setTimeout(update, 150);
	});
};

const toggleSeoText = () => {
	const holders = document.querySelectorAll('.seo-text__text');
	if (holders.length) {
		holders.forEach(initSeoText);
	}
};

export { toggleSeoText };

document.getElementById('wpadminbar')?.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && event.target instanceof HTMLInputElement) {
        event.preventDefault();
        event.target.blur();
    }
});

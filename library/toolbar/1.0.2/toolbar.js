document.addEventListener('keydown', (event) => {
    const input = event.target;
    if (event.key === 'Escape' && input instanceof HTMLInputElement && input.closest('#wpadminbar')) {
        event.preventDefault();
        input.blur();
    }
});

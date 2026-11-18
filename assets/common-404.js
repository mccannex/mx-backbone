document.querySelectorAll('form[data-mx-clear-404]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.mxClear404)) {
            event.preventDefault();
        }
    });
});

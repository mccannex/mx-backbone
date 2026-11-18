for (const cell of document.querySelectorAll('#the-list td.column-title')) {
    const viewLink = cell.querySelector('.row-actions .view a');
    const title = cell.querySelector('strong');
    if (viewLink && title) {
        const permalink = viewLink.cloneNode(false);
        permalink.className = 'mx-backbone-page-permalink';
        const url = new URL(viewLink.href);
        permalink.textContent = url.origin === window.location.origin
            ? url.pathname + url.search + url.hash
            : url.href;
        title.after(permalink);
    }
    for (const state of cell.querySelectorAll('.post-state')) {
        state.textContent = state.textContent.replace(/^[,\s]+|[,\s]+$/g, '');
        state.classList.add('mx-backbone-page-status');
    }
}

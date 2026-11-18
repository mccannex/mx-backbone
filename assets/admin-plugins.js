const addPlugin = document.querySelector('#wpbody-content .page-title-action[href*="plugin-install.php"]');
if (addPlugin && window.mxBackbonePlugins) {
    const upload = document.createElement('a');
    upload.className = 'page-title-action';
    upload.href = mxBackbonePlugins.url;
    upload.textContent = mxBackbonePlugins.label;
    addPlugin.after(upload);
}

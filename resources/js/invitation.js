const field = document.getElementById('invitation-token');
if (field) {
    field.value = new URLSearchParams(location.hash.slice(1)).get('token') ?? '';
    history.replaceState(null, '', location.pathname);
    if (!field.value) {
        document.querySelector('#prepare-invitation button').disabled = true;
        document.getElementById('missing-token').hidden = false;
    }
}

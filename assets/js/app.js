document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (event.defaultPrevented) { return; }
        var button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.textContent = 'Please wait...';
        }
    });
});
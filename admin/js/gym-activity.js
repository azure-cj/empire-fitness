// gym-activity.js - small enhancements for filter UX
document.addEventListener('DOMContentLoaded', function () {
    // Submit form when user changes source or activity type (for faster UX)
    const sourceEl = document.getElementById('source');
    const typeEl = document.getElementById('activity_type');
    const fromEl = document.getElementById('from');
    const toEl = document.getElementById('to');

    function submitFilters() {
        // keep search input intact - submit the GET form
        document.getElementById('activityFilters').submit();
    }

    if (sourceEl) sourceEl.addEventListener('change', submitFilters);
    if (typeEl) typeEl.addEventListener('change', submitFilters);

    // optional: pressing Enter in search field will submit form (native behavior)
    // Basic client-side validation for date range
    const form = document.getElementById('activityFilters');
    if (form) {
        form.addEventListener('submit', function (e) {
            const fromVal = fromEl ? fromEl.value : '';
            const toVal = toEl ? toEl.value : '';
            if (fromVal && toVal && fromVal > toVal) {
                e.preventDefault();
                alert('The "From" date must be before or equal to the "To" date.');
            }
        });
    }
});
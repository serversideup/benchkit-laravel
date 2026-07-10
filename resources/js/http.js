// Fetch helpers for the app's same-origin JSON endpoints. Laravel's CSRF
// middleware accepts the XSRF-TOKEN cookie value in a header — the same
// mechanism axios uses — so plain fetch() calls work without an Inertia form.
export const xsrfToken = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

export const jsonHeaders = () => ({
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-XSRF-TOKEN': xsrfToken(),
});

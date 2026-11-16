// The Feedback form: a letter counter, the screenshot (chosen or pasted) with a preview, the screen size, and one
// click on Send. Without this script the form still works: choose a file, send.
const TYPES = ['image/png', 'image/jpeg', 'image/webp'];

const form = document.querySelector('[data-feedback-form]');
if (form) {
    const message = form.querySelector('[data-fb-message]');
    const count = form.querySelector('[data-fb-count]');
    const file = form.querySelector('[data-fb-file]');
    const preview = form.querySelector('[data-fb-preview]');
    const thumb = form.querySelector('[data-fb-thumb]');
    const remove = form.querySelector('[data-fb-remove]');
    const problem = form.querySelector('[data-fb-image-error]');
    const submit = form.querySelector('[data-fb-submit]');
    const viewport = form.querySelector('[data-fb-viewport]');
    const screen = form.querySelector('[data-fb-screen]');
    const maxBytes = Number(form.dataset.maxKb || 5120) * 1024;
    let url = null;

    const size = () => `${window.innerWidth}x${window.innerHeight}`;
    const stamp = () => {
        viewport.value = size();
        if (screen) {
            screen.textContent = size().replace('x', ' × ');
        }
    };
    stamp();
    window.addEventListener('resize', stamp);

    message.addEventListener('input', () => {
        count.textContent = message.value.length.toLocaleString('en-US');
    });

    const clear = () => {
        file.value = '';
        if (url) {
            URL.revokeObjectURL(url);
            url = null;
        }
        thumb.removeAttribute('src');
        preview.classList.add('hidden');
        preview.classList.remove('flex');
    };
    const warn = (text) => {
        problem.textContent = text;
        problem.classList.toggle('hidden', !text);
    };
    // Checks a picked or pasted file the way the server will, so a refusal comes before the upload.
    const take = (picked) => {
        warn('');
        if (!TYPES.includes(picked.type)) {
            clear();
            warn(form.dataset.msgType);
            return;
        }
        if (picked.size > maxBytes) {
            clear();
            warn(form.dataset.msgSize);
            return;
        }
        const list = new DataTransfer();
        list.items.add(picked);
        file.files = list.files;
        if (url) {
            URL.revokeObjectURL(url);
        }
        url = URL.createObjectURL(picked);
        thumb.src = url;
        preview.classList.remove('hidden');
        preview.classList.add('flex');
    };

    file.addEventListener('change', () => {
        if (file.files && file.files[0]) {
            take(file.files[0]);
        } else {
            clear();
        }
    });
    remove.addEventListener('click', () => {
        warn('');
        clear();
    });
    // Ctrl+V anywhere on the page: an image on the clipboard becomes the screenshot; pasted text is left alone.
    document.addEventListener('paste', (event) => {
        const data = event.clipboardData;
        if (!data || data.getData('text/plain')) {
            return;
        }
        const image = Array.from(data.files || []).find((f) => f.type.startsWith('image/'));
        if (image) {
            event.preventDefault();
            take(image);
        }
    });

    // One click sends once: the button is off from the moment the form really goes (a wait for the security check does not count).
    form.addEventListener('submit', (event) => {
        stamp();
        setTimeout(() => {
            if (!event.defaultPrevented) {
                submit.disabled = true;
            }
        }, 0);
    });
    // Back to the page from the thank-you page: the button must work again.
    window.addEventListener('pageshow', () => {
        submit.disabled = false;
    });
}

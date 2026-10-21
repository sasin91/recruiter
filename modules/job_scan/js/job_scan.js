// Job scan: loads the next posts when the "Show more" link scrolls into
// view (or is clicked), and puts them, with the next link, in its place.
// Without JS the link opens the next posts as a page.

const list = document.querySelector('.posts');

async function load(link) {
    if (link.dataset.loading) return;
    link.dataset.loading = '1';
    link.textContent = 'Loading…';
    try {
        const response = await fetch(link.dataset.more, { headers: { Accept: 'text/html' } });
        if (!response.ok) throw new Error(response.statusText);
        const batch = document.createElement('template'); // server HTML
        batch.innerHTML = await response.text(); // server HTML
        // A post shows once, even when a batch is loaded twice.
        for (const post of batch.content.querySelectorAll('article[id]')) {
            if (document.getElementById(post.id)) post.remove();
        }
        observer.unobserve(link);
        link.replaceWith(batch.content);
        watch();
    } catch {
        delete link.dataset.loading;
        link.textContent = "Couldn't load more. Try again";
    }
}

const observer = new IntersectionObserver((entries) => {
    for (const entry of entries) {
        if (entry.isIntersecting) load(entry.target);
    }
}, { rootMargin: '600px 0px' });

function watch() {
    const link = list?.querySelector('a.more[data-more]');
    if (!link || link.dataset.watched) return;
    link.dataset.watched = '1';
    link.addEventListener('click', (event) => {
        event.preventDefault();
        load(link);
    });
    observer.observe(link);
}

watch();

// ---------- live list ----------
//
// Trongate MX polls job_scan/latest/{id} (the first batch, best first) into
// the hidden #posts-latest every 30 seconds while the page is in view, and
// the page reads it at once when it's back in view (another tab, a phone
// waking, the back/forward cache). When the answer differs from the top of
// the list (a new post, a post matched or changed), it becomes the list,
// with its own Show more: posts loaded below are dropped and load again in
// their new order as the list is scrolled. Keeping them would keep a cursor
// from before the change, which skips posts whose score moved them above it.

const poller = document.getElementById('posts-latest');
const stale = document.getElementById('posts-stale');

window.jobScanMerge = () => {
    const latest = poller.querySelector('.posts-latest');
    poller.replaceChildren();
    if (!latest) {
        // The sign-in page, most likely: the session ran out.
        stale.textContent = "The list can't update: you may have been signed out. Reload the page.";
        stale.hidden = false;
        return;
    }
    stale.hidden = true;
    const posts = [...latest.querySelectorAll(':scope > article[id]')];
    const top = [...list.querySelectorAll(':scope > article[id]')].slice(0, posts.length);
    const same = top.length === posts.length && posts.every((post, i) => top[i].outerHTML === post.outerHTML);
    if (!same && (posts.length || top.length)) {
        // An unchanged post stays the same element, so it doesn't flicker.
        const kept = (node) => {
            const shown = node.id ? document.getElementById(node.id) : null;
            return shown && shown.outerHTML === node.outerHTML ? shown : node;
        };
        list.replaceChildren(...[...latest.childNodes].map(kept));
        watch();
    }
    const none = document.getElementById('no-posts');
    if (none) none.hidden = list.querySelector('article[id]') !== null;
};

let readAt = 0;

// Polling stopped, the list read now, polling started again.
function readNow() {
    if (Date.now() - readAt < 2000) return;
    readAt = Date.now();
    TrongateMX.stopPolling(poller);
    poller.click();
    TrongateMX.startPolling(poller, '30s');
}

if (poller) {
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) TrongateMX.stopPolling(poller);
        else readNow();
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted && !document.hidden) readNow();
    });
    // Trongate MX starts the polling once the page has loaded; a page opened
    // in a background tab waits until it's looked at.
    document.addEventListener('DOMContentLoaded', () => {
        if (document.hidden) TrongateMX.stopPolling(poller);
    });
}
